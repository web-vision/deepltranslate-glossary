<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Client;

use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use DeepL\GlossaryLanguagePair;
use WebVision\Deepltranslate\Core\ClientInterface as DeepltranslateCoreClientInterface;
use WebVision\Deepltranslate\Core\Exception\ApiKeyNotSetException;

/**
 * Describes required implementation for Glossary API v2 compatible client implementations.
 * @depreacted in favour of upcoming GlossaryV3Interace and APIv3 handling.
 * @internal and not public API yet. Kept for refactoring towards APIv3.
 */
interface GlossaryAPIV2ClientInterface extends DeepltranslateCoreClientInterface
{
    /**
     * @return GlossaryLanguagePair[]
     *
     * @throws ApiKeyNotSetException
     */
    public function getGlossaryLanguagePairs(): array;

    /**
     * @return GlossaryInfo[]
     *
     * @throws ApiKeyNotSetException
     * @deprecated This function is deprecated in favour of multilingual glossaries and will be removed in future versions
     */
    public function getAllGlossaries(): array;

    /**
     * @throws ApiKeyNotSetException
     * @deprecated This function is deprecated in favour of multilingual glossaries and will be removed in future versions
     */
    public function getGlossary(string $glossaryId): ?GlossaryInfo;

    /**
     * @param array<int, array{source: string, target: string}> $entries
     *
     * @throws ApiKeyNotSetException
     * @deprecated This function is deprecated in favour of multilingual glossaries and will be removed in future versions
     */
    public function createGlossary(
        string $glossaryName,
        string $sourceLang,
        string $targetLang,
        array $entries
    ): GlossaryInfo;

    /**
     * @throws ApiKeyNotSetException
     * @deprecated This function is deprecated in favour of multilingual glossaries and will be removed in future versions
     */
    public function deleteGlossary(string $glossaryId): void;

    /**
     * @throws ApiKeyNotSetException
     * @deprecated This function is deprecated in favour of multilingual glossaries and will be removed in future versions
     */
    public function getGlossaryEntries(string $glossaryId): ?GlossaryEntries;
}
