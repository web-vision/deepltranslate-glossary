<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Client;

use DeepL\DeepLException;
use DeepL\GlossaryLanguagePair;
use DeepL\GlossaryNotFoundException;
use DeepL\MultilingualGlossaryDictionaryEntries;
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

    #[Test]
    public function glossaryIsDeleted(): void
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        $glossaryId = $this->createGlossaryWithDeEnDictionary(__FUNCTION__)->glossaryId;

        $client->deleteGlossary($glossaryId);

        // A deleted glossary must not resolve any longer. Without the exception the sync would
        // keep a dangling glossary id in the local record.
        $this->expectException(GlossaryNotFoundException::class);
        $client->getGlossary($glossaryId);
    }

    #[Test]
    public function replacingDictionaryDropsRemovedTerms(): void
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        $glossaryService = $this->get(MultilingualGlossaryService::class);
        $glossaryId = $this->createGlossaryWithDeEnDictionary(__FUNCTION__)->glossaryId;

        $dictionaryInfo = $client->replaceDictionary(
            $glossaryId,
            $glossaryService->createDictionary(
                'de',
                'en',
                [
                    'Hallo' => 'Hello',
                ]
            )
        );

        // Replacing must not merge: the previously stored second entry has to be gone, otherwise
        // a term deleted in TYPO3 would survive in the DeepL dictionary forever.
        $this->assertInstanceOf(MultilingualGlossaryDictionaryInfo::class, $dictionaryInfo);
        $this->assertEquals('de', $dictionaryInfo->sourceLang);
        $this->assertEquals('en', $dictionaryInfo->targetLang);
        $this->assertEquals(1, $dictionaryInfo->entryCount);
    }

    #[Test]
    public function dictionaryIsDeleted(): void
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        $glossaryService = $this->get(MultilingualGlossaryService::class);
        $glossaryId = $this->createGlossaryWithDeEnDictionary(__FUNCTION__)->glossaryId;
        $client->replaceDictionary(
            $glossaryId,
            $glossaryService->createDictionary(
                'en',
                'fr',
                [
                    'Hello' => 'Bonjour',
                ]
            )
        );

        $client->deleteDictionary($glossaryId, 'en', 'fr');

        $glossary = $client->getGlossary($glossaryId);
        $this->assertCount(1, $glossary->dictionaries);
        $remainingDictionary = array_pop($glossary->dictionaries);
        $this->assertInstanceOf(MultilingualGlossaryDictionaryInfo::class, $remainingDictionary);
        $this->assertEquals('de', $remainingDictionary->sourceLang);
        $this->assertEquals('en', $remainingDictionary->targetLang);
    }

    #[Test]
    public function glossaryEntriesAreRetrieved(): void
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        $glossaryId = $this->createGlossaryWithDeEnDictionary(__FUNCTION__)->glossaryId;

        $entries = $client->getGlossaryEntries($glossaryId, 'de', 'en');

        $this->assertIsArray($entries);
        $this->assertContainsOnlyInstancesOf(MultilingualGlossaryDictionaryEntries::class, $entries);
        $dictionary = array_shift($entries);
        $this->assertInstanceOf(MultilingualGlossaryDictionaryEntries::class, $dictionary);
        $this->assertSame(
            [
                'Hallo' => 'Hello',
                'Fachhochschule' => 'University of Applied Sciences',
            ],
            $dictionary->entries
        );
    }

    #[Test]
    public function unknownGlossaryRaisesExceptionInsteadOfEmptyGlossaryInfo(): void
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);

        // Guards the error contract: a failing call must not be answered with a placeholder
        // MultilingualGlossaryInfo, which a caller cannot distinguish from a successful one.
        // An unknown id is rejected as a bad request, only a deleted glossary raises the more
        // specific GlossaryNotFoundException, see self::glossaryIsDeleted().
        $this->expectException(DeepLException::class);
        $client->getGlossary('4b1cbd1a-0000-0000-0000-000000000000');
    }

    private function createGlossaryWithDeEnDictionary(string $testName): MultilingualGlossaryInfo
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        $glossaryService = $this->get(MultilingualGlossaryService::class);

        return $client->createGlossary(
            'Deepl-Client-Create-Function-Test:' . $testName,
            [
                $glossaryService->createDictionary(
                    'de',
                    'en',
                    [
                        'Hallo' => 'Hello',
                        'Fachhochschule' => 'University of Applied Sciences',
                    ]
                ),
            ],
        );
    }
}
