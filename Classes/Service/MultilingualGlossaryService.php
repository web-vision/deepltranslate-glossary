<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Service;

use DeepL\DeepLException;
use DeepL\GlossaryNotFoundException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryInfo;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;

/**
 * This service defines helper methods for handling with multilingual Glossaries
 */
#[Autoconfigure(public: true)]
final class MultilingualGlossaryService
{
    public function __construct(
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
        $dictionaryData = $this->glossaryRepository->getDictionaryDataForSync($pageId);
        $record = $this->glossaryRepository->findOrCreateGlossaryRecord($pageId);

        if ($dictionaryData === []) {
            $this->dropGlossary($record);
            return;
        }

        $dictionaries = [];
        foreach ($dictionaryData as $dictionary) {
            $dictionaries[] = $this->createDictionary(
                $dictionary['sourceLanguage'],
                $dictionary['targetLanguage'],
                $dictionary['entries']
            );
        }

        $information = $this->pushDictionaries($record, $dictionaries);
        $this->glossaryRepository->updateGlossaryRecord($information, (int)$record['uid'], $pageId);
    }

    /**
     * @param array{uid: int, glossary_id: string, glossary_name: string} $record
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     *
     * @throws DeepLException
     */
    private function pushDictionaries(array $record, array $dictionaries): MultilingualGlossaryInfo
    {
        if ($record['glossary_id'] === '') {
            return $this->client->createGlossary($record['glossary_name'], $dictionaries);
        }

        try {
            return $this->updateExistingGlossary($record['glossary_id'], $dictionaries);
        } catch (GlossaryNotFoundException) {
            // The glossary was removed on the DeepL side, so the stored id is worthless and the
            // folder is published again as a new glossary.
            return $this->client->createGlossary($record['glossary_name'], $dictionaries);
        }
    }

    /**
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     *
     * @throws DeepLException
     */
    private function updateExistingGlossary(string $glossaryId, array $dictionaries): MultilingualGlossaryInfo
    {
        $configuredPairs = [];
        foreach ($dictionaries as $dictionary) {
            // Replacing instead of merging, so a term removed in TYPO3 disappears at DeepL too.
            $this->client->replaceDictionary($glossaryId, $dictionary);
            $configuredPairs[] = $dictionary->sourceLang . '-' . $dictionary->targetLang;
        }

        foreach ($this->client->getGlossary($glossaryId)->dictionaries as $remoteDictionary) {
            $pair = $remoteDictionary->sourceLang . '-' . $remoteDictionary->targetLang;
            if (in_array($pair, $configuredPairs, true)) {
                continue;
            }
            $this->client->deleteDictionary($glossaryId, $remoteDictionary->sourceLang, $remoteDictionary->targetLang);
        }

        return $this->client->getGlossary($glossaryId);
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

    /**
     * $entries is an associative array where key is the source language and value is the target language
     * For example (The source language is English, target language is German):
     * [
     *     'hello' => 'Guten Tag',
     *     'University of Applied Sciences' => 'Fachhochschule',
     * ]
     *
     * Terms are trimmed and unusable pairs are dropped, see {@see self::sanitizeEntries()}.
     *
     * @param array<string, string> $entries
     * @throws DeepLException
     */
    public function createDictionary(
        string $sourceLanguage,
        string $targetLanguage,
        array $entries,
    ): MultilingualGlossaryDictionaryEntries {
        return new MultilingualGlossaryDictionaryEntries(
            $sourceLanguage,
            $targetLanguage,
            $this->sanitizeEntries($entries)
        );
    }

    /**
     * Trims both sides of a term pair and drops pairs which are unusable afterwards.
     *
     * DeepL rejects a term without non-whitespace characters and answers the whole request with
     * an error. A single term an editor left unfilled would therefore abort the synchronisation
     * of an entire glossary folder, so such pairs are skipped instead.
     *
     * Trimming can let two source terms collapse into the same key, in which case the last pair
     * wins. Reducing the terms to what DeepL accepts is the wanted behaviour here.
     *
     * @param array<string, string> $entries
     * @return array<string, string>
     */
    private function sanitizeEntries(array $entries): array
    {
        $sanitizedEntries = [];
        foreach ($entries as $source => $target) {
            $trimmedSource = trim((string)$source);
            $trimmedTarget = trim($target);
            if ($trimmedSource === '' || $trimmedTarget === '') {
                continue;
            }
            $sanitizedEntries[$trimmedSource] = $trimmedTarget;
        }

        return $sanitizedEntries;
    }
}
