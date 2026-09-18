<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Unit\Client;

use DateTime;
use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WebVision\Deepltranslate\Core\Client\DeepLClientFactoryInterface;
use WebVision\Deepltranslate\Core\Client\DeepLClientInterface;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV2Client;

final class GlossaryAPIV2ClientTermLengthTest extends UnitTestCase
{
    /**
     * A pair exceeding DeepL's 1024 UTF-8 byte limit must not abort the synchronization of the
     * whole glossary folder, the same way an empty pair is dropped instead of sent. This mirrors
     * `MultilingualGlossaryService::sanitizeEntries()` added for the Glossary API v3 in
     * web-vision/deepltranslate-glossary#52, for the still supported Glossary API v2.
     */
    #[Test]
    public function oversizedPairIsDroppedFromPayloadAndLogged(): void
    {
        $oversizedTerm = str_repeat('ü', 513);
        self::assertGreaterThan(1024, strlen($oversizedTerm));

        $deeplClient = $this->createMock(DeepLClientInterface::class);
        $deeplClient->expects(self::once())
            ->method('createGlossary')
            ->with(
                'name',
                'de',
                'en',
                self::callback(
                    static fn (GlossaryEntries $entries): bool => $entries->getEntries() === ['hallo' => 'hello']
                )
            )
            ->willReturn(new GlossaryInfo('id', 'name', true, 'de', 'en', new DateTime(), 1));

        $clientFactory = $this->createMock(DeepLClientFactoryInterface::class);
        $clientFactory->method('create')->willReturn($deeplClient);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('1024 UTF-8 bytes'));

        $client = new GlossaryAPIV2Client($logger, $clientFactory);
        $client->createGlossary(
            'name',
            'de',
            'en',
            [
                ['source' => 'hallo', 'target' => 'hello'],
                ['source' => $oversizedTerm, 'target' => 'target text'],
            ]
        );
    }
}
