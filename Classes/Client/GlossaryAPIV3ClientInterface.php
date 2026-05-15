<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Client;

use DeepL\GlossaryLanguagePair;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryInfo;
use WebVision\Deepltranslate\Core\ClientInterface as DeepltranslateCoreClientInterface;
use WebVision\Deepltranslate\Core\Exception\ApiKeyNotSetException;

/**
 * Describes required implementation for Glossary API v3 compatible client implementations.
 * @internal and not public API yet. Methods will be added in minor versions implementing this API version.
 */
interface GlossaryAPIV3ClientInterface extends DeepltranslateCoreClientInterface
{
    /**
     * @return GlossaryLanguagePair[]
     *
     * @throws ApiKeyNotSetException
     */
    public function getGlossaryLanguagePairs(): array;

    /**
     * @return MultilingualGlossaryInfo[]
     *
     * @throws ApiKeyNotSetException
     */
    public function getAllGlossaries(): array;

    /**
     * @throws ApiKeyNotSetException
     */
    public function getGlossary(string $glossaryId): MultilingualGlossaryInfo;

    /**
     * @param string $glossaryName
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     * @return MultilingualGlossaryInfo
     */
    public function createGlossary(
        string $glossaryName,
        array $dictionaries,
    ): MultilingualGlossaryInfo;

    /**
     * @param non-empty-string $glossaryId
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     */
    public function updateGlossary(
        string $glossaryId,
        array $dictionaries,
        ?string $name = null,
    ): MultilingualGlossaryInfo;
}
