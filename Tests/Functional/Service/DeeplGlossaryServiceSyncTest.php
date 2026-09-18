<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollision;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollisionReason;
use WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class DeeplGlossaryServiceSyncTest extends AbstractDeepLTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'DE' => [
            'id' => 0,
            'title' => 'Deutsch',
            'locale' => 'de_DE.UTF-8',
            'iso' => 'de',
            'hrefLang' => 'de-DE',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => '',
            ],
        ],
        'EN-GB' => [
            'id' => 1,
            'title' => 'English (UK)',
            'locale' => 'en_GB.UTF-8',
            'iso' => 'en',
            'hrefLang' => 'en-GB',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'EN-GB',
            ],
        ],
        'EN-US' => [
            'id' => 2,
            'title' => 'English (US)',
            'locale' => 'en_US.UTF-8',
            'iso' => 'en',
            'hrefLang' => 'en-US',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'EN-US',
            ],
        ],
        'EN-US-PREFERRED' => [
            'id' => 2,
            'title' => 'English (US)',
            'locale' => 'en_US.UTF-8',
            'iso' => 'en',
            'hrefLang' => 'en-US',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'EN-US',
                'deeplGlossaryTerms' => 'preferred',
            ],
        ],
    ];

    protected array $testExtensionsToLoad = [
        'web-vision/deeplcom-deepl-php',
        'web-vision/deepl-base',
        'web-vision/deepltranslate-core',
        'web-vision/deepltranslate-glossary',
        __DIR__ . '/../Fixtures/Extensions/test_services_override',
    ];

    public static function collisionDataProvider(): \Generator
    {
        yield 'unresolved collision of EN-GB and EN-US is returned' => [
            'usLanguage' => 'EN-US',
            'expectedCollisions' => [
                [
                    'languageCode' => 'en',
                    'reason' => GlossaryLanguageCollisionReason::NoPreferredLanguage,
                ],
            ],
        ];
        yield 'collision resolved by a preferred language is not returned' => [
            'usLanguage' => 'EN-US-PREFERRED',
            'expectedCollisions' => [],
        ];
    }

    /**
     * @param non-empty-string $usLanguage
     * @param list<array{languageCode: string, reason: GlossaryLanguageCollisionReason}> $expectedCollisions
     */
    #[Test]
    #[DataProvider('collisionDataProvider')]
    public function syncGlossariesReturnsUnresolvedLanguageCodeCollisions(
        string $usLanguage,
        array $expectedCollisions,
    ): void {
        $this->writeSiteConfiguration(
            identifier: 'acme',
            site: $this->buildSiteConfiguration(rootPageId: 1),
            languages: [
                $this->buildDefaultLanguageConfiguration('DE', '/'),
                $this->buildLanguageConfiguration('EN-GB', '/en-gb/'),
                $this->buildLanguageConfiguration($usLanguage, '/en-us/'),
            ],
        );
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/SharedLanguageCode/sharedLanguageCodeEnglishVariants.csv');
        $this->setUpBackendUser(1);

        $collisions = $this->get(DeeplGlossaryService::class)->syncGlossaries(2);

        self::assertSame($expectedCollisions, array_map(
            static fn (GlossaryLanguageCollision $collision): array => [
                'languageCode' => $collision->languageCode,
                'reason' => $collision->reason,
            ],
            $collisions
        ));
    }
}
