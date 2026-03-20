<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Client;

use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use DeepL\GlossaryLanguagePair;
use PHPUnit\Framework\Attributes\Test;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV2ClientInterface;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class GlossaryV2ClientTest extends AbstractDeepLTestCase
{
    #[Test]
    public function checkResponseFromGlossaryLanguagePairs(): void
    {
        $client = $this->get(GlossaryAPIV2ClientInterface::class);
        $response = $client->getGlossaryLanguagePairs();

        $this->assertIsArray($response);
        $this->assertContainsOnlyInstancesOf(GlossaryLanguagePair::class, $response);
    }

    #[Test]
    public function checkResponseFromCreateGlossary(): void
    {
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
    public function checkResponseGetAllGlossaries(): void
    {
        $client = $this->get(GlossaryAPIV2ClientInterface::class);
        $response = $client->getAllGlossaries();

        $this->assertIsArray($response);
        $this->assertContainsOnlyInstancesOf(GlossaryInfo::class, $response);
    }

    #[Test]
    public function checkResponseFromGetGlossary(): void
    {
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
    public function checkGlossaryDeletedNotCatchable(): void
    {
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
    public function checkResponseFromGetGlossaryEntries(): void
    {
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
