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
            $entries
        );
    }
}
