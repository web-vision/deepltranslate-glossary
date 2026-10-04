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
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;

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
    ) {
    }

    /**
     * Mirrors a glossary folder onto its persistent DeepL glossary.
     *
     * The glossary of a folder is created once and edited afterwards, so the glossary id stays
     * stable and pages referencing it keep working across synchronisations.
     *
     * @throws DeepLException
     */
    public function syncGlossary(int $pageId): void
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
