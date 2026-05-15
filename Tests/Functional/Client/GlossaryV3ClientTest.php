<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Client;

use DeepL\GlossaryLanguagePair;
use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use PHPUnit\Framework\Attributes\Test;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class GlossaryV3ClientTest extends AbstractDeepLTestCase
{
    #[Test]
    public function checkResponseFromGlossaryLanguagePairs(): void
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        $response = $client->getGlossaryLanguagePairs();

        $this->assertIsArray($response);
        $this->assertContainsOnlyInstancesOf(GlossaryLanguagePair::class, $response);
    }

    #[Test]
    public function checkResponseFromCreateGlossary(): void
    {
        /** @var GlossaryAPIV3ClientInterface $client */
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        /** @var MultilingualGlossaryService $glossaryService */
        $glossaryService = $this->get(MultilingualGlossaryService::class);
        $deEnDictionary = $glossaryService->createDictionary(
            'de',
            'en',
            [
                'Hallo' => 'Hello',
                'Fachhochschule' => 'University of Applied Sciences',
            ]
        );
        $glossaryName = 'Deepl-Client-Create-Function-Test:' . __FUNCTION__;
        $response = $client->createGlossary(
            $glossaryName,
            [
                $deEnDictionary,
            ],
        );

        $this->assertInstanceOf(MultilingualGlossaryInfo::class, $response);
        $this->assertIsString($response->glossaryId);
        $this->assertEquals($glossaryName, $response->name);
        $this->assertIsArray($response->dictionaries);
        $this->assertCount(1, $response->dictionaries);
        $dictionary = array_pop($response->dictionaries);
        $this->assertInstanceOf(MultilingualGlossaryDictionaryInfo::class, $dictionary);
        $this->assertEquals('de', $dictionary->sourceLang);
        $this->assertEquals('en', $dictionary->targetLang);
        $this->assertInstanceOf(\DateTime::class, $response->creationTime);
    }

    #[Test]
    public function glossaryIsUpdated(): void
    {
        /** @var GlossaryAPIV3ClientInterface $client */
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        /** @var MultilingualGlossaryService $glossaryService */
        $glossaryService = $this->get(MultilingualGlossaryService::class);
        $deEnDictionary = $glossaryService->createDictionary(
            'de',
            'en',
            [
                'Hallo' => 'Hello',
                'Fachhochschule' => 'University of Applied Sciences',
            ]
        );
        $glossaryName = 'Deepl-Client-Create-Function-Test:' . __FUNCTION__;
        $createResponse = $client->createGlossary(
            $glossaryName,
            [
                $deEnDictionary,
            ],
        );

        /** @var non-empty-string $glossaryId */
        $glossaryId = $createResponse->glossaryId;
        $enFrDictionary = $glossaryService->createDictionary(
            'en',
            'fr',
            [
                'Hello' => 'Bonjour',
                'University of Applied Sciences' => 'Université des sciences appliquées',
            ]
        );

        $updateResponse = $client->updateGlossary(
            $glossaryId,
            [$enFrDictionary],
        );
        $this->assertInstanceOf(MultilingualGlossaryInfo::class, $updateResponse);
        $this->assertIsString($updateResponse->glossaryId);
        $this->assertEquals($glossaryName, $updateResponse->name);
        $this->assertIsArray($updateResponse->dictionaries);
        $this->assertCount(2, $updateResponse->dictionaries);
        // @todo check if new dictionaries are always set to first array position or if this is random correct
        $firstDictionary = array_pop($updateResponse->dictionaries);
        $this->assertInstanceOf(MultilingualGlossaryDictionaryInfo::class, $firstDictionary);
        $this->assertEquals('en', $firstDictionary->sourceLang);
        $this->assertEquals('fr', $firstDictionary->targetLang);
        $secondDictionary = array_pop($updateResponse->dictionaries);
        $this->assertInstanceOf(MultilingualGlossaryDictionaryInfo::class, $secondDictionary);
        $this->assertEquals('de', $secondDictionary->sourceLang);
        $this->assertEquals('en', $secondDictionary->targetLang);
        $this->assertInstanceOf(\DateTime::class, $updateResponse->creationTime);
    }
}
