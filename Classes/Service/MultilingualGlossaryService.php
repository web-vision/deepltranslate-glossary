<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Service;

use DeepL\DeepLException;
use DeepL\GlossaryNotFoundException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Locking\Exception\LockAcquireWouldBlockException;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Exception\GlossaryFolderNotSyncableException;

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
        private readonly SiteFinder $siteFinder,
        private readonly LockFactory $lockFactory,
    ) {
    }

    /**
     * Mirrors a glossary folder onto its persistent DeepL glossary.
     *
     * The glossary of a folder is created once and edited afterwards, so the glossary id stays
     * stable and pages referencing it keep working across synchronisations.
     *
     * @throws DeepLException
     * @throws GlossaryFolderNotSyncableException
     */
    public function syncGlossary(int $pageId): void
    {
        $this->assertSyncableFolder($pageId);
        $lock = $this->acquireFolderLock($pageId);
        try {
            $this->synchroniseFolder($pageId);
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws DeepLException
     * @throws GlossaryFolderNotSyncableException
     */
    private function synchroniseFolder(int $pageId): void
    {
        $dictionaries = $this->buildDictionaries($this->glossaryRepository->getDictionaryDataForSync($pageId, $this->getPossibleLanguagePairs()));
        if ($dictionaries === []) {
            $record = $this->glossaryRepository->findGlossaryRecord($pageId);
            if ($record !== null) {
                $this->dropGlossary($record);
            }
            return;
        }

        $record = $this->glossaryRepository->findOrCreateGlossaryRecord($pageId);
        $information = $this->pushDictionaries($record, $dictionaries);
        $this->storeSyncedGlossary($information, $record, $pageId);
    }

    /**
     * Two synchronisations of the same folder at once, like the scheduler and an editor, would
     * both create a glossary at DeepL and leave one of them orphaned.
     *
     * @throws GlossaryFolderNotSyncableException
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

        throw new GlossaryFolderNotSyncableException(
            sprintf('Glossary folder %d is being synchronised already.', $pageId),
            1791134376
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
        try {
            $this->siteFinder->getSiteByPageId($pageId);
        } catch (SiteNotFoundException) {
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
     * @param array<int, array{sourceLanguage: string, targetLanguage: string, entries: array<string, string>}> $dictionaryData
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
        $glossary = $this->findRemoteGlossary($record['glossary_id']);
        if ($glossary === null) {
            return $this->client->createGlossary($record['glossary_name'], $dictionaries);
        }

        return $this->updateExistingGlossary($glossary, $dictionaries);
    }

    /**
     * @throws DeepLException
     */
    private function findRemoteGlossary(string $glossaryId): ?MultilingualGlossaryInfo
    {
        if ($glossaryId === '') {
            return null;
        }

        try {
            return $this->client->getGlossary($glossaryId);
        } catch (GlossaryNotFoundException) {
            // The glossary was removed on the DeepL side, so the stored id is worthless and the
            // folder is published again as a new glossary.
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
            $pair = $dictionary->sourceLang . '-' . $dictionary->targetLang;
            $syncedDictionaries[$pair] = $this->client->replaceDictionary($glossary->glossaryId, $dictionary);
        }

        foreach ($glossary->dictionaries as $remoteDictionary) {
            if (isset($syncedDictionaries[$remoteDictionary->sourceLang . '-' . $remoteDictionary->targetLang])) {
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
     * @param array{uid: int, glossary_id: string, glossary_name: string} $record
     *
     * @throws DeepLException
     */
    private function dropGlossary(array $record): void
    {
        if ($record['glossary_id'] !== '') {
            try {
                $this->client->deleteGlossary($record['glossary_id']);
            } catch (GlossaryNotFoundException) {
                // Already gone on the DeepL side, only the local state needs cleaning up.
            }
        }

        $this->glossaryRepository->resetGlossaryRecord((int)$record['uid']);
    }
}
