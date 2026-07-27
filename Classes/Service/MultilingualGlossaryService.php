<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Service;

use DeepL\DeepLException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * This service defines helper methods for handling with multilingual Glossaries
 */
#[Autoconfigure(public: true)]
final class MultilingualGlossaryService
{
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
     * @param non-empty-string $sourceLanguage
     * @param non-empty-string $targetLanguage
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
