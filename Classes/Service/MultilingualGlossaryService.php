<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Service;

use DeepL\DeepLException;
use DeepL\GlossaryNotFoundException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Locking\Exception\LockAcquireWouldBlockException;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use WebVision\Deepltranslate\Core\Exception\ApiKeyNotSetException;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollision;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossarySyncResult;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Exception\GlossaryFolderNotSyncableException;
use WebVision\Deepltranslate\Glossary\Exception\GlossarySyncInProgressException;
use WebVision\Deepltranslate\Glossary\Upgrade\MigrateToMultilingualGlossaryWizard;

/**
 * This service defines helper methods for handling with multilingual Glossaries
 */
#[Autoconfigure(public: true)]
final class MultilingualGlossaryService
{
    public function __construct(
        #[Autowire(service: 'cache.deepltranslate_glossary')]
        private readonly FrontendInterface $cache,
        private readonly GlossaryAPIV3ClientInterface $client,
        private readonly GlossaryRepository $glossaryRepository,
        private readonly Registry $registry,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Mirrors a glossary folder onto its persistent DeepL glossary.
     *
     * The glossary of a folder is created once and edited afterwards, so the glossary id stays
     * stable and pages referencing it keep working across synchronisations.
     *
     * Site languages sharing a glossary language code the site configuration does not decide
     * unambiguously are logged and returned, so the caller can report them.
     *
     * @throws ApiKeyNotSetException
     * @throws DeepLException
     * @throws GlossaryFolderNotSyncableException
     * @throws GlossarySyncInProgressException
     */
    public function syncGlossary(int $pageId): GlossarySyncResult
    {
        $this->assertSyncableFolder($pageId);
        $this->assertMigratedFolder($pageId);
        $lock = $this->acquireFolderLock($pageId);
        try {
            return $this->synchroniseFolder($pageId);
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws DeepLException
     * @throws GlossaryFolderNotSyncableException
     */
    private function synchroniseFolder(int $pageId): GlossarySyncResult
    {
        $syncInformation = $this->glossaryRepository->getDictionaryDataForSync($pageId, $this->getPossibleLanguagePairs());
        $this->logCollisions($pageId, $syncInformation->collisions);
        $dictionaries = $this->buildDictionaries($syncInformation->dictionaries);
        if ($dictionaries === []) {
            $this->dropGlossaryOfEmptiedFolder($pageId);
            return new GlossarySyncResult(false, $syncInformation->collisions);
        }

        $record = $this->glossaryRepository->findOrCreateGlossaryRecord($pageId);
        $information = $this->pushDictionaries($record, $dictionaries);
        $this->storeSyncedGlossary($information, $record, $pageId);

        return new GlossarySyncResult(true, $syncInformation->collisions);
    }

    /**
     * @param list<GlossaryLanguageCollision> $collisions
     */
    private function logCollisions(int $pageId, array $collisions): void
    {
        foreach ($collisions as $collision) {
            $this->logger->warning(
                'Glossary folder {pageId}: site languages share the glossary language code "{languageCode}" ({reason}).'
                . ' Terms of site language {selectedLanguageId} are used, terms of site languages {ignoredLanguageIds} are ignored.',
                [
                    'pageId' => $pageId,
                    'languageCode' => $collision->languageCode,
                    'reason' => $collision->reason->value,
                    'selectedLanguageId' => $collision->selectedLanguage->getLanguageId(),
                    'ignoredLanguageIds' => implode(', ', array_map(
                        static fn (SiteLanguage $language): int => $language->getLanguageId(),
                        $collision->ignoredLanguages
                    )),
                ]
            );
        }
    }

    /**
     * Two synchronisations of the same folder at once, like the scheduler and an editor, would
     * both create a glossary at DeepL and leave one of them orphaned.
     *
     * @throws GlossarySyncInProgressException
     */
    private function acquireFolderLock(int $pageId): LockingStrategyInterface
    {
        $mode = LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK;
        $lock = $this->lockFactory->createLocker('deepltranslate_glossary_sync_' . $pageId, $mode);
        try {
            if ($lock->acquire($mode)) {
                return $lock;
            }
        } catch (LockAcquireWouldBlockException) {
        }

        throw new GlossarySyncInProgressException(
            sprintf('Glossary folder %d is being synchronised already.', $pageId),
            1791321875
        );
    }

    /**
     * A glossary created at DeepL for this synchronisation is removed again when its id cannot be
     * stored, as the next synchronisation would otherwise create another one beside it.
     *
     * @param array{uid: int, glossary_id: string, glossary_name: string} $record
     *
     * @throws DBALException
     */
    private function storeSyncedGlossary(MultilingualGlossaryInfo $information, array $record, int $pageId): void
    {
        try {
            $this->glossaryRepository->updateGlossaryRecord($information, (int)$record['uid'], $pageId);
        } catch (DBALException $exception) {
            if ($information->glossaryId !== $record['glossary_id']) {
                $this->deleteCreatedGlossary($information->glossaryId);
            }
            throw $exception;
        }
    }

    private function deleteCreatedGlossary(string $glossaryId): void
    {
        try {
            $this->client->deleteGlossary($glossaryId);
        } catch (DeepLException) {
            // The failure to store the glossary is the one to report, the client logged this one.
        }
    }

    /**
     * @throws GlossaryFolderNotSyncableException
     */
    private function assertSyncableFolder(int $pageId): void
    {
        if (!$this->glossaryRepository->isGlossaryFolder($pageId)) {
            throw new GlossaryFolderNotSyncableException(
                sprintf('Page %d is no visible folder set up as glossary.', $pageId),
                1791123486
            );
        }
        if (!$this->glossaryRepository->belongsToSite($pageId)) {
            throw new GlossaryFolderNotSyncableException(
                sprintf('Glossary folder %d belongs to no site, so its languages are unknown.', $pageId),
                1791123489
            );
        }
    }

    /**
     * Returns the language pairs DeepL supports glossaries for, as target languages by source
     * language.
     *
     * DeepL always supports some pairs, so an empty answer is neither cached nor trusted from the
     * cache, and is fetched again instead.
     *
     * @return array<string, array<array-key, string>>
     *
     * @throws DeepLException
     */
    public function getPossibleLanguagePairs(): array
    {
        $cacheIdentifier = 'wv-deepl-glossary-pairs';
        $languagePairs = $this->cache->get($cacheIdentifier);
        if (is_array($languagePairs) && $languagePairs !== []) {
            return $languagePairs;
        }

        $languagePairs = [];
        foreach ($this->client->getGlossaryLanguagePairs() as $languagePair) {
            $languagePairs[$languagePair->sourceLang][] = $languagePair->targetLang;
        }
        if ($languagePairs !== []) {
            $this->cache->set($cacheIdentifier, $languagePairs);
        }

        return $languagePairs;
    }

    /**
     * The terms come cleaned from {@see GlossaryRepository::getDictionaryDataForSync()}, which
     * also skips a pair left without any term, as DeepL refuses a dictionary without entries.
     *
     * @param list<array{sourceLanguage: string, targetLanguage: string, entries: array<string, string>}> $dictionaryData
     * @return MultilingualGlossaryDictionaryEntries[]
     *
     * @throws DeepLException
     */
    private function buildDictionaries(array $dictionaryData): array
    {
        $dictionaries = [];
        foreach ($dictionaryData as $dictionary) {
            $dictionaries[] = new MultilingualGlossaryDictionaryEntries(
                $dictionary['sourceLanguage'],
                $dictionary['targetLanguage'],
                $dictionary['entries']
            );
        }

        return $dictionaries;
    }

    /**
     * @param array{uid: int, glossary_id: string, glossary_name: string} $record
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     *
     * @throws DeepLException
     */
    private function pushDictionaries(array $record, array $dictionaries): MultilingualGlossaryInfo
    {
        $glossary = $this->findRemoteGlossary($record);
        if ($glossary === null) {
            return $this->client->createGlossary($record['glossary_name'], $dictionaries);
        }

        return $this->updateExistingGlossary($glossary, $dictionaries);
    }

    /**
     * A glossary removed on the DeepL side leaves the stored id worthless, and the folder is
     * published again as a new glossary. The folder is detached from the removed glossary
     * first, so it does not stay attached to it when creating the new one fails, for example
     * at the glossary limit of the account, or when the new id cannot be stored.
     *
     * @param array{uid: int, glossary_id: string, glossary_name: string} $record
     *
     * @throws DBALException
     * @throws DeepLException
     */
    private function findRemoteGlossary(array $record): ?MultilingualGlossaryInfo
    {
        if ($record['glossary_id'] === '') {
            return null;
        }

        try {
            return $this->client->getGlossary($record['glossary_id']);
        } catch (GlossaryNotFoundException) {
            $this->glossaryRepository->resetGlossaryRecord((int)$record['uid']);
            return null;
        }
    }

    /**
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     *
     * @throws DeepLException
     */
    private function updateExistingGlossary(MultilingualGlossaryInfo $glossary, array $dictionaries): MultilingualGlossaryInfo
    {
        $syncedDictionaries = [];
        foreach ($dictionaries as $dictionary) {
            // Replacing instead of merging, so a term removed in TYPO3 disappears at DeepL too.
            $pair = $this->buildPairKey($dictionary->sourceLang, $dictionary->targetLang);
            $syncedDictionaries[$pair] = $this->client->replaceDictionary($glossary->glossaryId, $dictionary);
        }

        foreach ($glossary->dictionaries as $remoteDictionary) {
            if (isset($syncedDictionaries[$this->buildPairKey($remoteDictionary->sourceLang, $remoteDictionary->targetLang)])) {
                continue;
            }
            $this->deleteObsoleteDictionary($glossary->glossaryId, $remoteDictionary);
        }

        return new MultilingualGlossaryInfo(
            $glossary->glossaryId,
            $glossary->name,
            $glossary->creationTime,
            array_values($syncedDictionaries)
        );
    }

    /**
     * DeepL treats language codes case-insensitively, so "EN" and "en" name the same dictionary,
     * which must not be deleted as obsolete right after it was replaced.
     */
    private function buildPairKey(string $sourceLanguage, string $targetLanguage): string
    {
        return strtolower($sourceLanguage) . '-' . strtolower($targetLanguage);
    }

    /**
     * @throws DeepLException
     */
    private function deleteObsoleteDictionary(string $glossaryId, MultilingualGlossaryDictionaryInfo $dictionary): void
    {
        try {
            $this->client->deleteDictionary($glossaryId, $dictionary->sourceLang, $dictionary->targetLang);
        } catch (GlossaryNotFoundException) {
            // Removed in the meantime, for example by a concurrent synchronisation.
        }
    }

    /**
     * The upgrade wizard detaches every glossary stored for a folder of the API v2 and lists it
     * for removal with `deepl:glossary:cleanup --legacy`, a glossary published by this
     * synchronisation in the meantime included, so such a folder waits for it.
     *
     * @todo Remove together with {@see MigrateToMultilingualGlossaryWizard}.
     *
     * @throws GlossaryFolderNotSyncableException
     */
    private function assertMigratedFolder(int $pageId): void
    {
        if ($this->registry->get('installUpdate', MigrateToMultilingualGlossaryWizard::class, false)
            || !$this->glossaryRepository->hasGlossaryRecordOfApiV2($pageId)
        ) {
            return;
        }

        throw new GlossaryFolderNotSyncableException(
            sprintf(
                'Glossary folder %d still holds glossaries of the DeepL glossary API v2. Run the upgrade wizard "%s" first.',
                $pageId,
                'Migrate glossaries to the DeepL glossary API v3'
            ),
            1791130218
        );
    }

    /**
     * Only a folder without any term is emptied on purpose. Terms which form no usable pair
     * point at a broken setup instead, for example a removed page translation, and dropping the
     * glossary then would silently stop translations from using it.
     *
     * @throws DeepLException
     * @throws GlossaryFolderNotSyncableException
     */
    private function dropGlossaryOfEmptiedFolder(int $pageId): void
    {
        if ($this->glossaryRepository->hasTerms($pageId)) {
            throw new GlossaryFolderNotSyncableException(
                sprintf(
                    'The terms of glossary folder %d form no term pair DeepL supports. Check that the folder and its terms are translated.',
                    $pageId
                ),
                1791129095
            );
        }

        $record = $this->glossaryRepository->findGlossaryRecord($pageId);
        if ($record !== null) {
            $this->dropGlossary($record);
        }
    }

    /**
     * The folder is detached first, so translations stop using the glossary before it is gone at
     * DeepL. When detaching fails, the glossary at DeepL is left untouched instead of the folder
     * pointing at a removed glossary. A glossary DeepL refuses to remove is left behind detached,
     * the failure is reported and the glossary can be removed with the cleanup command.
     *
     * @param array{uid: int, glossary_id: string, glossary_name: string} $record
     *
     * @throws DBALException
     * @throws DeepLException
     */
    private function dropGlossary(array $record): void
    {
        $this->glossaryRepository->resetGlossaryRecord((int)$record['uid']);
        if ($record['glossary_id'] === '') {
            return;
        }

        try {
            $this->client->deleteGlossary($record['glossary_id']);
        } catch (GlossaryNotFoundException) {
            // Already gone on the DeepL side, only the local state needed cleaning up.
        }
    }
}
