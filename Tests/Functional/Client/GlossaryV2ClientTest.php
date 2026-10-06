<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Client;

use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use DeepL\GlossaryLanguagePair;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Test;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV2ClientInterface;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * The client of the glossary API v2 is deprecated, every public method triggers a deprecation.
 */
final class GlossaryV2ClientTest extends AbstractDeepLTestCase
{
    #[Test]
    #[IgnoreDeprecations]
    public function checkResponseFromGlossaryLanguagePairs(): void
    {
        $this->expectUserDeprecationMessageMatches('/GlossaryAPIV2Client::getGlossaryLanguagePairs\(\) is deprecated/');
        $client = $this->get(GlossaryAPIV2ClientInterface::class);
        $response = $client->getGlossaryLanguagePairs();

        $this->assertIsArray($response);
        $this->assertContainsOnlyInstancesOf(GlossaryLanguagePair::class, $response);
    }

    #[Test]
    #[IgnoreDeprecations]
    public function checkResponseFromCreateGlossary(): void
    {
        $this->expectUserDeprecationMessageMatches('/GlossaryAPIV2Client::createGlossary\(\) is deprecated/');
        $client = $this->get(GlossaryAPIV2ClientInterface::class);
        $response = $client->createGlossary(
            'Deepl-Client-Create-Function-Test:' . __FUNCTION__,
            'de',
            'en',
            [
                0 => [
                    'source' => 'hallo Welt',
                    'target' => 'hello world',
                ],
            ],
        );

        $this->assertInstanceOf(GlossaryInfo::class, $response);
        $this->assertSame(1, $response->entryCount);
        $this->assertIsString($response->glossaryId);
        $this->assertInstanceOf(\DateTime::class, $response->creationTime);
    }

    #[Test]
    #[IgnoreDeprecations]
    public function checkResponseGetAllGlossaries(): void
    {
        $this->expectUserDeprecationMessageMatches('/GlossaryAPIV2Client::getAllGlossaries\(\) is deprecated/');
        $client = $this->get(GlossaryAPIV2ClientInterface::class);
        $response = $client->getAllGlossaries();

        $this->assertIsArray($response);
        $this->assertContainsOnlyInstancesOf(GlossaryInfo::class, $response);
    }

    #[Test]
    #[IgnoreDeprecations]
    public function checkResponseFromGetGlossary(): void
    {
        $this->expectUserDeprecationMessageMatches('/GlossaryAPIV2Client::getGlossary\(\) is deprecated/');
        $client = $this->get(GlossaryAPIV2ClientInterface::class);
        $glossary = $client->createGlossary(
            'Deepl-Client-Create-Function-Test:' . __FUNCTION__,
            'de',
            'en',
            [
                0 => [
                    'source' => 'hallo Welt',
                    'target' => 'hello world',
                ],
            ],
        );

        $response = $client->getGlossary($glossary->glossaryId);

        $this->assertInstanceOf(GlossaryInfo::class, $response);
        $this->assertSame($glossary->glossaryId, $response->glossaryId);
        $this->assertSame(1, $response->entryCount);
    }

    #[Test]
    #[IgnoreDeprecations]
    public function checkGlossaryDeletedNotCatchable(): void
    {
        $this->expectUserDeprecationMessageMatches('/GlossaryAPIV2Client::deleteGlossary\(\) is deprecated/');
        $client = $this->get(GlossaryAPIV2ClientInterface::class);
        $glossary = $client->createGlossary(
            'Deepl-Client-Create-Function-Test' . __FUNCTION__,
            'de',
            'en',
            [
                0 => [
                    'source' => 'hallo Welt',
                    'target' => 'hello world',
                ],
            ],
        );

        $glossaryId = $glossary->glossaryId;

        $client->deleteGlossary($glossaryId);

        $this->assertNull($client->getGlossary($glossaryId));
    }

    #[Test]
    #[IgnoreDeprecations]
    public function checkResponseFromGetGlossaryEntries(): void
    {
        $this->expectUserDeprecationMessageMatches('/GlossaryAPIV2Client::getGlossaryEntries\(\) is deprecated/');
        $client = $this->get(GlossaryAPIV2ClientInterface::class);
        $glossary = $client->createGlossary(
            'Deepl-Client-Create-Function-Test:' . __FUNCTION__,
            'de',
            'en',
            [
                0 => [
                    'source' => 'hallo Welt',
                    'target' => 'hello world',
                ],
            ],
        );

        $response = $client->getGlossaryEntries($glossary->glossaryId);

        $this->assertInstanceOf(GlossaryEntries::class, $response);
        $this->assertSame(1, count($response->getEntries()));
    }
}
