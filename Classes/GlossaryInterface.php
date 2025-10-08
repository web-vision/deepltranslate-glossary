<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary;

use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use WebVision\Deepltranslate\Core\ClientInterface;

interface GlossaryInterface extends ClientInterface
{

    /**
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     */
    public function createMultilingualGlossary(string $name, array $dictionaries = []): MultilingualGlossaryInfo;

    /**
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     */
    public function updateMultilingualGlossary(string $glossaryId, array $dictionaries, string $newName = ''): MultilingualGlossaryInfo;
    public function replaceMultilingualGlossary(string $glossaryId, MultilingualGlossaryDictionaryEntries $dictionaries): MultilingualGlossaryDictionaryInfo;

    public function deleteMultilingualGlossary(string $glossaryId): void;

    public function deleteGlossaryDictionary(string $glossaryId, string $sourceLanguage, string $targetLanguage): void;

    /**
     * @return MultilingualGlossaryInfo[]
     */
    public function listMultilingualGlossaries(): array;
}
