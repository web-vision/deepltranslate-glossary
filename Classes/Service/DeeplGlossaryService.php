<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Service;

use DateTime;
use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use DeepL\GlossaryLanguagePair;
use Doctrine\DBAL\Driver\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV2ClientInterface;
use WebVision\Deepltranslate\Glossary\Domain\Dto\Glossary;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollision;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Exception\FailedToCreateGlossaryException;
use WebVision\Deepltranslate\Glossary\Exception\GlossaryEntriesNotExistException;

#[Autoconfigure(public: true)]
final readonly class DeeplGlossaryService
{
    public function __construct(
        #[Autowire(service: 'cache.deepltranslate_glossary')]
        private FrontendInterface $cache,
        private GlossaryAPIV2ClientInterface $client,
        private GlossaryRepository $glossaryRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Calls the glossary-Endpoint and return Json-response as an array
     *
     * @return GlossaryLanguagePair[]
     */
    public function listLanguagePairs(): array
    {
        return $this->client->getGlossaryLanguagePairs();
    }

    /**
     * Calls the glossary-Endpoint and return Json-response as an array
     *
     * @return GlossaryInfo[]
     */
    public function listGlossaries(): array
    {
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
        $this->client->deleteGlossary($glossaryId);
    }

    /**
     * Gets information about a glossary
     */
    public function glossaryInformation(string $glossaryId): ?GlossaryInfo
    {
        return $this->client->getGlossary($glossaryId);
    }

    /**
     * Fetch glossary entries and format them as an associative array [source => target]
     */
    public function glossaryEntries(string $glossaryId): ?GlossaryEntries
    {
        return $this->client->getGlossaryEntries($glossaryId);
    }

    /**
     * @return array<string, array<array-key, string>>
     */
    public function getPossibleGlossaryLanguageConfig(): array
    {
        $cacheIdentifier = 'wv-deepl-glossary-pairs';
        if (($pairMappingArray = $this->cache->get($cacheIdentifier)) !== false) {
            return $pairMappingArray;
        }

        $possiblePairs = $this->listLanguagePairs();

        $pairMappingArray = [];
        foreach ($possiblePairs as $possiblePair) {
            $pairMappingArray[$possiblePair->sourceLang][] = $possiblePair->targetLang;
        }

        $this->cache->set($cacheIdentifier, $pairMappingArray);

        return $pairMappingArray;
    }

    /**
     * Synchronizes all glossaries of a glossary folder with DeepL.
     *
     * @return list<GlossaryLanguageCollision> site languages sharing a glossary language code the site
     *     configuration does not resolve unambiguously, to be reported to the user
     *
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Exception
     * @throws FailedToCreateGlossaryException
     */
    public function syncGlossaries(int $uid): array
    {
        $syncInformation = $this->glossaryRepository->getGlossarySyncInformation($uid);
        $this->logCollisions($uid, $syncInformation->collisions);
        if (empty($syncInformation->glossaries)) {
            throw new FailedToCreateGlossaryException(
                'Glossary can not created, the TYPO3 information are invalide.',
                1714987594661
            );
        }

        foreach ($syncInformation->glossaries as $glossaryInformation) {
            $this->syncGlossary($glossaryInformation);
        }

        return $syncInformation->collisions;
    }

    private function syncGlossary(Glossary $glossaryInformation): void
    {
        if ($glossaryInformation->glossaryId !== '') {
            $this->deleteGlossary($glossaryInformation->glossaryId);
        }

        try {
            $glossary = $this->createGlossary(
                $glossaryInformation->name,
                $glossaryInformation->entries,
                $glossaryInformation->sourceLanguage,
                $glossaryInformation->targetLanguage
            );
        } catch (GlossaryEntriesNotExistException) {
            $glossary = new GlossaryInfo(
                '',
                '',
                false,
                '',
                '',
                new DateTime(),
                0
            );
        }

        $this->glossaryRepository->updateLocalGlossary(
            $glossary,
            $glossaryInformation->uid
        );
    }

    /**
     * @param list<GlossaryLanguageCollision> $collisions
     */
    private function logCollisions(int $pageId, array $collisions): void
    {
        foreach ($collisions as $collision) {
            $this->logger->warning(
                'Glossary folder {pageId}: site languages share the glossary language code "{languageCode}" ({reason}).'
                . ' Terms of site language {selectedLanguageId} are used, terms of site languages {ignoredLanguageIds} are ignored.',
                [
                    'pageId' => $pageId,
                    'languageCode' => $collision->languageCode,
                    'reason' => $collision->reason->value,
                    'selectedLanguageId' => $collision->selectedLanguage->getLanguageId(),
                    'ignoredLanguageIds' => implode(', ', array_map(
                        static fn (SiteLanguage $language): int => $language->getLanguageId(),
                        $collision->ignoredLanguages
                    )),
                ]
            );
        }
    }
}
