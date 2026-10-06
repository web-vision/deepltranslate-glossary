<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Fixtures\Client;

use DeepL\DeepLException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use Psr\Log\LoggerInterface;
use WebVision\Deepltranslate\Core\Client\DeepLClientFactoryInterface;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3Client;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;

/**
 * Behaves like the real client talking to the mock server, except for what a test sets up: a
 * request DeepL answers with an error, or language codes DeepL answers in upper case. Every
 * request is recorded, to see which requests a caller made.
 */
final class InterceptingGlossaryClient implements GlossaryAPIV3ClientInterface
{
    private GlossaryAPIV3Client $client;

    /**
     * @var array<string, DeepLException>
     */
    private array $failures = [];

    private bool $upperCaseLanguageCodes = false;

    /**
     * @var list<string>
     */
    public array $requests = [];

    public function __construct(
        LoggerInterface $logger,
        DeepLClientFactoryInterface $clientFactory,
    ) {
        $this->client = new GlossaryAPIV3Client($logger, $clientFactory);
    }

    /**
     * @param string $method a method of {@see GlossaryAPIV3ClientInterface}
     */
    public function failOn(string $method, DeepLException $exception): self
    {
        $this->failures[$method] = $exception;

        return $this;
    }

    /**
     * Answers with the language codes of every dictionary in upper case, which DeepL treats
     * like the lower case ones.
     */
    public function answerWithUpperCaseLanguageCodes(): self
    {
        $this->upperCaseLanguageCodes = true;

        return $this;
    }

    public function getGlossaryLanguagePairs(): array
    {
        $this->intercept(__FUNCTION__);
        return $this->client->getGlossaryLanguagePairs();
    }

    public function getAllGlossaries(): array
    {
        $this->intercept(__FUNCTION__);
        return $this->client->getAllGlossaries();
    }

    public function getGlossary(string $glossaryId): MultilingualGlossaryInfo
    {
        $this->intercept(__FUNCTION__);
        return $this->convertGlossary($this->client->getGlossary($glossaryId));
    }

    public function createGlossary(string $glossaryName, array $dictionaries): MultilingualGlossaryInfo
    {
        $this->intercept(__FUNCTION__);
        return $this->convertGlossary($this->client->createGlossary($glossaryName, $dictionaries));
    }

    public function updateGlossary(string $glossaryId, array $dictionaries, ?string $name = null): MultilingualGlossaryInfo
    {
        $this->intercept(__FUNCTION__);
        return $this->convertGlossary($this->client->updateGlossary($glossaryId, $dictionaries, $name));
    }

    public function replaceDictionary(
        string $glossaryId,
        MultilingualGlossaryDictionaryEntries $dictionary,
    ): MultilingualGlossaryDictionaryInfo {
        $this->intercept(__FUNCTION__);
        return $this->convertDictionary($this->client->replaceDictionary($glossaryId, $dictionary));
    }

    public function deleteGlossary(string $glossaryId): void
    {
        $this->intercept(__FUNCTION__);
        $this->client->deleteGlossary($glossaryId);
    }

    public function deleteDictionary(string $glossaryId, string $sourceLanguage, string $targetLanguage): void
    {
        $this->intercept(__FUNCTION__);
        $this->client->deleteDictionary($glossaryId, $sourceLanguage, $targetLanguage);
    }

    public function getGlossaryEntries(string $glossaryId, string $sourceLanguage, string $targetLanguage): array
    {
        $this->intercept(__FUNCTION__);
        return $this->client->getGlossaryEntries($glossaryId, $sourceLanguage, $targetLanguage);
    }

    /**
     * @throws DeepLException
     */
    private function intercept(string $method): void
    {
        $this->requests[] = $method;
        if (isset($this->failures[$method])) {
            throw $this->failures[$method];
        }
    }

    private function convertGlossary(MultilingualGlossaryInfo $glossary): MultilingualGlossaryInfo
    {
        if (!$this->upperCaseLanguageCodes) {
            return $glossary;
        }

        return new MultilingualGlossaryInfo(
            $glossary->glossaryId,
            $glossary->name,
            $glossary->creationTime,
            array_map($this->convertDictionary(...), $glossary->dictionaries)
        );
    }

    private function convertDictionary(MultilingualGlossaryDictionaryInfo $dictionary): MultilingualGlossaryDictionaryInfo
    {
        if (!$this->upperCaseLanguageCodes) {
            return $dictionary;
        }

        return new MultilingualGlossaryDictionaryInfo(
            strtoupper($dictionary->sourceLang),
            strtoupper($dictionary->targetLang),
            $dictionary->entryCount
        );
    }
}
