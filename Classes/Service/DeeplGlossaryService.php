<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Service;

use DeepL\DeepLException;
use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use DeepL\GlossaryLanguagePair;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV2ClientInterface;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollision;
use WebVision\Deepltranslate\Glossary\Exception\GlossaryEntriesNotExistException;
use WebVision\Deepltranslate\Glossary\Exception\GlossaryFolderNotSyncableException;

/**
 * Glossary handling based on the DeepL glossary API v2.
 *
 * @deprecated since 6.1, will be removed in 7.0. The extension synchronises through
 *             {@see MultilingualGlossaryService::syncGlossary()} and the glossary API v3. This
 *             class is kept for consumers still relying on it and is no longer used internally.
 *             Its synchronisation goes through the API v3 as well. Every public method triggers
 *             a deprecation notice.
 */
#[Autoconfigure(public: true)]
final readonly class DeeplGlossaryService
{
    public function __construct(
        private GlossaryAPIV2ClientInterface $client,
        private MultilingualGlossaryService $multilingualGlossaryService,
    ) {
    }

    /**
     * Calls the glossary-Endpoint and return Json-response as an array
     *
     * @return GlossaryLanguagePair[]
     */
    public function listLanguagePairs(): array
    {
        trigger_error(
            'DeeplGlossaryService::listLanguagePairs() is deprecated since 6.1 and will be removed in 7.0.'
            . ' Use the DeepL PHP library deeplcom/deepl-php to access the glossary API directly.',
            E_USER_DEPRECATED
        );
        return $this->client->getGlossaryLanguagePairs();
    }

    /**
     * Calls the glossary-Endpoint and return Json-response as an array
     *
     * @return GlossaryInfo[]
     */
    public function listGlossaries(): array
    {
        trigger_error(
            'DeeplGlossaryService::listGlossaries() is deprecated since 6.1 and will be removed in 7.0.'
            . ' Use the DeepL PHP library deeplcom/deepl-php to access the glossary API directly.',
            E_USER_DEPRECATED
        );
        return $this->client->getAllGlossaries();
    }

    /**
     * Creates a glossary, entries must be formatted as [sourceText => entryText] e.g: ['Hallo' => 'Hello']
     *
     * @param array<int, array{source: string, target: string}> $entries
     *
     * @throws GlossaryEntriesNotExistException
     */
    public function createGlossary(
        string $name,
        array $entries,
        string $sourceLang = 'de',
        string $targetLang = 'en'
    ): GlossaryInfo {
        trigger_error(
            'DeeplGlossaryService::createGlossary() is deprecated since 6.1 and will be removed in 7.0.'
            . ' Use the DeepL PHP library deeplcom/deepl-php to access the glossary API directly.',
            E_USER_DEPRECATED
        );
        if (empty($entries)) {
            throw new GlossaryEntriesNotExistException(
                'Glossary Entries are required',
                1677169192
            );
        }

        return $this->client->createGlossary($name, $sourceLang, $targetLang, $entries);
    }

    /**
     * Deletes a glossary
     *
     * @param string $glossaryId
     */
    public function deleteGlossary(string $glossaryId): void
    {
        trigger_error(
            'DeeplGlossaryService::deleteGlossary() is deprecated since 6.1 and will be removed in 7.0.'
            . ' Use the DeepL PHP library deeplcom/deepl-php to access the glossary API directly.',
            E_USER_DEPRECATED
        );
        $this->client->deleteGlossary($glossaryId);
    }

    /**
     * Gets information about a glossary
     */
    public function glossaryInformation(string $glossaryId): ?GlossaryInfo
    {
        trigger_error(
            'DeeplGlossaryService::glossaryInformation() is deprecated since 6.1 and will be removed in 7.0.'
            . ' Use the DeepL PHP library deeplcom/deepl-php to access the glossary API directly.',
            E_USER_DEPRECATED
        );
        return $this->client->getGlossary($glossaryId);
    }

    /**
     * Fetch glossary entries and format them as an associative array [source => target]
     */
    public function glossaryEntries(string $glossaryId): ?GlossaryEntries
    {
        trigger_error(
            'DeeplGlossaryService::glossaryEntries() is deprecated since 6.1 and will be removed in 7.0.'
            . ' Use the DeepL PHP library deeplcom/deepl-php to access the glossary API directly.',
            E_USER_DEPRECATED
        );
        return $this->client->getGlossaryEntries($glossaryId);
    }

    /**
     * @return array<string, array<array-key, string>>
     */
    public function getPossibleGlossaryLanguageConfig(): array
    {
        trigger_error(
            'DeeplGlossaryService::getPossibleGlossaryLanguageConfig() is deprecated since 6.1 and will be removed in 7.0.'
            . ' Use the DeepL PHP library deeplcom/deepl-php to access the glossary API directly.',
            E_USER_DEPRECATED
        );
        try {
            return $this->multilingualGlossaryService->getPossibleLanguagePairs();
        } catch (DeepLException) {
            // The API v2 handling reported a failure as no language pair at all.
            return [];
        }
    }

    /**
     * Synchronises a glossary folder through the glossary API v3, see
     * {@see MultilingualGlossaryService::syncGlossary()}. A folder left without any term pair
     * loses its glossary at DeepL, and a failure throws instead of being logged.
     *
     * @return list<GlossaryLanguageCollision> site languages sharing a glossary language code the site
     *     configuration does not resolve unambiguously, to be reported to the user
     *
     * @throws DeepLException
     * @throws GlossaryFolderNotSyncableException
     */
    public function syncGlossaries(int $uid): array
    {
        trigger_error(
            'DeeplGlossaryService::syncGlossaries() is deprecated since 6.1 and will be removed in 7.0.'
            . ' Use MultilingualGlossaryService::syncGlossary() instead.',
            E_USER_DEPRECATED
        );
        return $this->multilingualGlossaryService->syncGlossary($uid)->collisions;
    }
}
