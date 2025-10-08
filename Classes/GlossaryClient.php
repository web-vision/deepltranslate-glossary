<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary;

use DeepL\DeepLException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use WebVision\Deepltranslate\Core\Exception\ApiKeyNotSetException;
use WebVision\Deepltranslate\Glossary\GlossaryInterface;

class GlossaryClient implements GlossaryInterface
{
    /**
     * @throws DeepLException
     * @throws ApiKeyNotSetException
     */
    public function createMultilingualGlossary(string $name, array $dictionaries = []): MultilingualGlossaryInfo
    {
        return $this->getTranslator()->createMultilingualGlossary($name, $dictionaries);
    }

    /**
     * @throws DeepLException
     * @throws ApiKeyNotSetException
     */
    public function updateMultilingualGlossary(string $glossaryId, array $dictionaries, string $newName = ''): MultilingualGlossaryInfo
    {
        return $this->getTranslator()->updateMultilingualGlossary(
            $glossaryId,
            $newName !== '' ? $newName : null,
            $dictionaries
        );
    }

    /**
     * @throws DeepLException
     * @throws ApiKeyNotSetException
     */
    public function replaceMultilingualGlossary(string $glossaryId, MultilingualGlossaryDictionaryEntries $dictionaries): MultilingualGlossaryDictionaryInfo
    {
        return $this->getTranslator()->replaceMultilingualGlossaryDictionary($glossaryId, $dictionaries);
    }

    /**
     * @throws DeepLException
     * @throws ApiKeyNotSetException
     */
    public function deleteMultilingualGlossary(string $glossaryId): void
    {
        $this->getTranslator()->deleteMultilingualGlossary($glossaryId);
    }

    /**
     * @throws DeepLException
     * @throws ApiKeyNotSetException
     */
    public function listMultilingualGlossaries(): array
    {
        return $this->getTranslator()->listMultilingualGlossaries();
    }

    /**
     * @throws DeepLException
     * @throws ApiKeyNotSetException
     */
    public function deleteGlossaryDictionary(string $glossaryId, string $sourceLanguage, string $targetLanguage): void
    {
        $this->getTranslator()->deleteMultilingualGlossaryDictionary(
            $glossaryId,
            null,
            $sourceLanguage,
            $targetLanguage
        );
    }
}
