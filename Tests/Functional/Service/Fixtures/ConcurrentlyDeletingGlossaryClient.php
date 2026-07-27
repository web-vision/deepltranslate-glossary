<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Service\Fixtures;

use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use Psr\Log\LoggerInterface;
use WebVision\Deepltranslate\Core\Client\DeepLClientFactoryInterface;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3Client;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;

/**
 * Behaves like the real client, except that every dictionary is removed by somebody else right
 * before it is deleted, so DeepL answers the deletion with a 404.
 */
final class ConcurrentlyDeletingGlossaryClient implements GlossaryAPIV3ClientInterface
{
    private GlossaryAPIV3Client $client;

    public function __construct(
        LoggerInterface $logger,
        DeepLClientFactoryInterface $clientFactory,
    ) {
        $this->client = new GlossaryAPIV3Client($logger, $clientFactory);
    }

    public function getGlossaryLanguagePairs(): array
    {
        return $this->client->getGlossaryLanguagePairs();
    }

    public function getAllGlossaries(): array
    {
        return $this->client->getAllGlossaries();
    }

    public function getGlossary(string $glossaryId): MultilingualGlossaryInfo
    {
        return $this->client->getGlossary($glossaryId);
    }

    public function createGlossary(string $glossaryName, array $dictionaries): MultilingualGlossaryInfo
    {
        return $this->client->createGlossary($glossaryName, $dictionaries);
    }

    public function updateGlossary(string $glossaryId, array $dictionaries, ?string $name = null): MultilingualGlossaryInfo
    {
        return $this->client->updateGlossary($glossaryId, $dictionaries, $name);
    }

    public function replaceDictionary(
        string $glossaryId,
        MultilingualGlossaryDictionaryEntries $dictionary,
    ): MultilingualGlossaryDictionaryInfo {
        return $this->client->replaceDictionary($glossaryId, $dictionary);
    }

    public function deleteGlossary(string $glossaryId): void
    {
        $this->client->deleteGlossary($glossaryId);
    }

    public function deleteDictionary(string $glossaryId, string $sourceLanguage, string $targetLanguage): void
    {
        $this->client->deleteDictionary($glossaryId, $sourceLanguage, $targetLanguage);
        $this->client->deleteDictionary($glossaryId, $sourceLanguage, $targetLanguage);
    }

    public function getGlossaryEntries(string $glossaryId, string $sourceLanguage, string $targetLanguage): array
    {
        return $this->client->getGlossaryEntries($glossaryId, $sourceLanguage, $targetLanguage);
    }
}
