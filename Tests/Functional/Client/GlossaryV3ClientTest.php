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
use Psr\Log\LogLevel;
use WebVision\Deepltranslate\Core\Client\DeepLClientFactoryInterface;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3Client;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;
use WebVision\Deepltranslate\Glossary\Tests\Functional\Client\Fixtures\CollectingLogger;
use WebVision\Deepltranslate\Glossary\Tests\Functional\Client\Fixtures\NotFoundAnsweringClientFactory;

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
        $deEnDictionary = new MultilingualGlossaryDictionaryEntries(
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
        $deEnDictionary = new MultilingualGlossaryDictionaryEntries(
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
        $enFrDictionary = new MultilingualGlossaryDictionaryEntries(
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
        $glossaryId = $this->createGlossaryWithDeEnDictionary(__FUNCTION__)->glossaryId;

        $dictionaryInfo = $client->replaceDictionary(
            $glossaryId,
            new MultilingualGlossaryDictionaryEntries(
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
        $glossaryId = $this->createGlossaryWithDeEnDictionary(__FUNCTION__)->glossaryId;
        $client->replaceDictionary(
            $glossaryId,
            new MultilingualGlossaryDictionaryEntries(
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

    #[Test]
    public function glossaryNotFoundIsLeftToTheCallerWithoutLoggingAnError(): void
    {
        $glossaryId = $this->createGlossaryWithDeEnDictionary(__FUNCTION__)->glossaryId;
        $this->get(GlossaryAPIV3ClientInterface::class)->deleteGlossary($glossaryId);
        $logger = new CollectingLogger();
        // Only the logger is replaced, to see what the client logs.
        $client = new GlossaryAPIV3Client($logger, $this->get(DeepLClientFactoryInterface::class));

        try {
            $client->getGlossary($glossaryId);
            self::fail('A deleted glossary has to be reported to the caller.');
        } catch (GlossaryNotFoundException) {
        }

        // Callers expect a removed glossary and recover from it, so it is no error to alert on.
        self::assertSame([LogLevel::DEBUG], $logger->levels);
        self::assertSame(['operation' => 'getGlossary', 'glossaryId' => $glossaryId], $logger->contexts[0]);
    }

    #[Test]
    public function notFoundAnswerToListingTheGlossariesIsLoggedAsError(): void
    {
        $logger = new CollectingLogger();
        $client = new GlossaryAPIV3Client($logger, new NotFoundAnsweringClientFactory($this->get(DeepLClientFactoryInterface::class)));

        try {
            $client->getAllGlossaries();
            self::fail('A request DeepL answers with "not found" has to be reported to the caller.');
        } catch (GlossaryNotFoundException) {
        }

        // No glossary is addressed, so "not found" points at a wrong server URL.
        self::assertSame([LogLevel::ERROR], $logger->levels);
        self::assertSame(['operation' => 'getAllGlossaries', 'glossaryId' => ''], $logger->contexts[0]);
    }

    #[Test]
    public function notFoundAnswerToCreatingAGlossaryIsLoggedAsError(): void
    {
        $logger = new CollectingLogger();
        $client = new GlossaryAPIV3Client($logger, new NotFoundAnsweringClientFactory($this->get(DeepLClientFactoryInterface::class)));

        try {
            $client->createGlossary('Glossary', [new MultilingualGlossaryDictionaryEntries('de', 'en', ['Hallo' => 'Hello'])]);
            self::fail('A request DeepL answers with "not found" has to be reported to the caller.');
        } catch (GlossaryNotFoundException) {
        }

        self::assertSame([LogLevel::ERROR], $logger->levels);
        self::assertSame(['operation' => 'createGlossary', 'glossaryId' => ''], $logger->contexts[0]);
    }

    #[Test]
    public function failingRequestIsLoggedAsError(): void
    {
        $logger = new CollectingLogger();
        $client = new GlossaryAPIV3Client($logger, $this->get(DeepLClientFactoryInterface::class));

        try {
            $client->getGlossary('4b1cbd1a-0000-0000-0000-000000000000');
            self::fail('An invalid glossary id has to be reported to the caller.');
        } catch (DeepLException) {
        }

        self::assertContains(LogLevel::ERROR, $logger->levels);
    }

    private function createGlossaryWithDeEnDictionary(string $testName): MultilingualGlossaryInfo
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);

        return $client->createGlossary(
            'Deepl-Client-Create-Function-Test:' . $testName,
            [
                new MultilingualGlossaryDictionaryEntries(
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
