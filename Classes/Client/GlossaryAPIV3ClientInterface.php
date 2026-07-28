<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Client;

use DeepL\DeepLException;
use DeepL\GlossaryLanguagePair;
use DeepL\GlossaryNotFoundException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use WebVision\Deepltranslate\Core\ClientInterface as DeepltranslateCoreClientInterface;
use WebVision\Deepltranslate\Core\Exception\ApiKeyNotSetException;

/**
 * Describes required implementation for Glossary API v3 compatible client implementations.
 *
 * Every method logs a failing API call and rethrows the original {@see DeepLException}. The
 * concrete subclasses carry the information a caller needs to react, most notably
 * {@see GlossaryNotFoundException} for a glossary removed on the DeepL side, so they must not
 * be wrapped into an extension specific exception.
 *
 * @internal and not public API yet. Methods will be added in minor versions implementing this API version.
 */
interface GlossaryAPIV3ClientInterface extends DeepltranslateCoreClientInterface
{
    /**
     * @return GlossaryLanguagePair[]
     *
     * @throws ApiKeyNotSetException
     * @throws DeepLException
     * @todo Switch to `GET /v3/languages?resource=glossary` once the DeepL mock server implements
     *       it and `deeplcom/deepl-php` exposes `getLanguagesForResource()` through the core client
     *       interface. The v2 endpoint is deprecated by DeepL and does not report newer languages.
     */
    public function getGlossaryLanguagePairs(): array;

    /**
     * @return MultilingualGlossaryInfo[]
     *
     * @throws ApiKeyNotSetException
     * @throws DeepLException
     */
    public function getAllGlossaries(): array;

    /**
     * @throws ApiKeyNotSetException
     * @throws GlossaryNotFoundException
     * @throws DeepLException
     */
    public function getGlossary(string $glossaryId): MultilingualGlossaryInfo;

    /**
     * @param string $glossaryName
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     *
     * @throws ApiKeyNotSetException
     * @throws DeepLException
     */
    public function createGlossary(
        string $glossaryName,
        array $dictionaries,
    ): MultilingualGlossaryInfo;

    /**
     * Merges the given dictionaries into the glossary and optionally renames it.
     *
     * Entries of an already existing language pair are merged, not replaced. Use
     * {@see self::replaceDictionary()} to mirror a TYPO3 glossary folder, otherwise terms
     * removed in TYPO3 remain in the DeepL dictionary.
     *
     * @param MultilingualGlossaryDictionaryEntries[] $dictionaries
     *
     * @throws ApiKeyNotSetException
     * @throws GlossaryNotFoundException
     * @throws DeepLException
     */
    public function updateGlossary(
        string $glossaryId,
        array $dictionaries,
        ?string $name = null,
    ): MultilingualGlossaryInfo;

    /**
     * Replaces the dictionary of the given language pair completely, or creates it when the
     * glossary does not contain that language pair yet.
     *
     *
     * @throws ApiKeyNotSetException
     * @throws GlossaryNotFoundException
     * @throws DeepLException
     */
    public function replaceDictionary(
        string $glossaryId,
        MultilingualGlossaryDictionaryEntries $dictionary,
    ): MultilingualGlossaryDictionaryInfo;

    /**
     * @throws ApiKeyNotSetException
     * @throws GlossaryNotFoundException
     * @throws DeepLException
     */
    public function deleteGlossary(string $glossaryId): void;

    /**
     * @throws ApiKeyNotSetException
     * @throws GlossaryNotFoundException
     * @throws DeepLException
     */
    public function deleteDictionary(
        string $glossaryId,
        string $sourceLanguage,
        string $targetLanguage,
    ): void;

    /**
     * @return MultilingualGlossaryDictionaryEntries[]
     *
     * @throws ApiKeyNotSetException
     * @throws GlossaryNotFoundException
     * @throws DeepLException
     */
    public function getGlossaryEntries(
        string $glossaryId,
        string $sourceLanguage,
        string $targetLanguage,
    ): array;
}
