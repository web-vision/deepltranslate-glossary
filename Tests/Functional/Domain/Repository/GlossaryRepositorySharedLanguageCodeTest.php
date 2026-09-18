<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Domain\Repository;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * Site languages sharing one DeepL glossary language code (de_DE/de_AT, en_GB/en_US) must not
 * make terms vanish from the synchronized glossary.
 *
 * @see https://github.com/web-vision/deepltranslate-glossary/issues/21
 */
final class GlossaryRepositorySharedLanguageCodeTest extends AbstractDeepLTestCase
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
        'DE-AT' => [
            'id' => 1,
            'title' => 'Deutsch (Österreich)',
            'locale' => 'de_AT.UTF-8',
            'iso' => 'de',
            'hrefLang' => 'de-AT',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'DE',
            ],
        ],
        'FR' => [
            'id' => 2,
            'title' => 'Français',
            'locale' => 'fr_FR.UTF-8',
            'iso' => 'fr',
            'hrefLang' => 'fr-FR',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'FR',
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
        'DE-AT-PREFERRED' => [
            'id' => 1,
            'title' => 'Deutsch (Österreich)',
            'locale' => 'de_AT.UTF-8',
            'iso' => 'de',
            'hrefLang' => 'de-AT',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'DE',
                'deeplGlossaryTerms' => 'preferred',
            ],
        ],
        'EN-GB-PREFERRED' => [
            'id' => 1,
            'title' => 'English (UK)',
            'locale' => 'en_GB.UTF-8',
            'iso' => 'en',
            'hrefLang' => 'en-GB',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'EN-GB',
                'deeplGlossaryTerms' => 'preferred',
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
        __DIR__ . '/../../Fixtures/Extensions/test_services_override',
    ];

    public static function sharedLanguageCodeDataProvider(): \Generator
    {
        yield 'default DE with DE-AT keeps the default terms as source' => [
            'fixture' => __DIR__ . '/../../Fixtures/SharedLanguageCode/sharedLanguageCodeGermanAustrian.csv',
            'languages' => ['DE', 'DE-AT', 'FR'],
            'sourceLanguage' => 'de',
            'targetLanguage' => 'fr',
            'expectedEntries' => [
                [
                    'source' => 'Schlagsahne',
                    'target' => 'crème fleurette',
                ],
                [
                    'source' => 'Kartoffel',
                    'target' => 'pomme de terre',
                ],
            ],
        ];
        yield 'EN-GB and EN-US without a choice use the lowest language id' => [
            'fixture' => __DIR__ . '/../../Fixtures/SharedLanguageCode/sharedLanguageCodeEnglishVariants.csv',
            'languages' => ['DE', 'EN-GB', 'EN-US'],
            'sourceLanguage' => 'de',
            'targetLanguage' => 'en',
            'expectedEntries' => [
                [
                    'source' => 'Aufzug',
                    'target' => 'lift',
                ],
                [
                    'source' => 'Gehweg',
                    'target' => 'pavement',
                ],
            ],
        ];
        yield 'EN-US glossary folder translation without terms does not wipe EN-GB terms' => [
            'fixture' => __DIR__ . '/../../Fixtures/SharedLanguageCode/sharedLanguageCodeEnglishVariantsWithoutUsTerms.csv',
            'languages' => ['DE', 'EN-GB', 'EN-US'],
            'sourceLanguage' => 'de',
            'targetLanguage' => 'en',
            'expectedEntries' => [
                [
                    'source' => 'Aufzug',
                    'target' => 'lift',
                ],
                [
                    'source' => 'Gehweg',
                    'target' => 'pavement',
                ],
            ],
        ];
        yield 'preferred EN-US wins over EN-GB and is not completed from EN-GB' => [
            'fixture' => __DIR__ . '/../../Fixtures/SharedLanguageCode/sharedLanguageCodeEnglishVariants.csv',
            'languages' => ['DE', 'EN-GB', 'EN-US-PREFERRED'],
            'sourceLanguage' => 'de',
            'targetLanguage' => 'en',
            'expectedEntries' => [
                [
                    'source' => 'Aufzug',
                    'target' => 'elevator',
                ],
            ],
        ];
        yield 'two preferred languages use the lowest language id' => [
            'fixture' => __DIR__ . '/../../Fixtures/SharedLanguageCode/sharedLanguageCodeEnglishVariants.csv',
            'languages' => ['DE', 'EN-GB-PREFERRED', 'EN-US-PREFERRED'],
            'sourceLanguage' => 'de',
            'targetLanguage' => 'en',
            'expectedEntries' => [
                [
                    'source' => 'Aufzug',
                    'target' => 'lift',
                ],
                [
                    'source' => 'Gehweg',
                    'target' => 'pavement',
                ],
            ],
        ];
        yield 'preferred DE-AT does not replace the default language as source' => [
            'fixture' => __DIR__ . '/../../Fixtures/SharedLanguageCode/sharedLanguageCodeGermanAustrian.csv',
            'languages' => ['DE', 'DE-AT-PREFERRED', 'FR'],
            'sourceLanguage' => 'de',
            'targetLanguage' => 'fr',
            'expectedEntries' => [
                [
                    'source' => 'Schlagsahne',
                    'target' => 'crème fleurette',
                ],
                [
                    'source' => 'Kartoffel',
                    'target' => 'pomme de terre',
                ],
            ],
        ];
    }

    /**
     * @param non-empty-list<non-empty-string> $languages
     * @param array<int, array{source: string, target: string}> $expectedEntries
     */
    #[Test]
    #[DataProvider('sharedLanguageCodeDataProvider')]
    public function getGlossaryInformationForSyncKeepsTermsOfLanguagesSharingALanguageCode(
        string $fixture,
        array $languages,
        string $sourceLanguage,
        string $targetLanguage,
        array $expectedEntries,
    ): void {
        $defaultLanguage = array_shift($languages);
        $languageConfiguration = [
            $this->buildDefaultLanguageConfiguration($defaultLanguage, '/'),
        ];
        foreach ($languages as $language) {
            $languageConfiguration[] = $this->buildLanguageConfiguration($language, '/' . strtolower($language) . '/');
        }
        $this->writeSiteConfiguration(
            identifier: 'acme',
            site: $this->buildSiteConfiguration(rootPageId: 1),
            languages: $languageConfiguration,
        );
        $this->importCSVDataSet($fixture);
        $this->setUpBackendUser(1);

        $glossaries = $this->get(GlossaryRepository::class)->getGlossaryInformationForSync(2);

        $entriesByPair = [];
        foreach ($glossaries as $glossary) {
            $entriesByPair[$glossary->sourceLanguage . '-' . $glossary->targetLanguage] = $glossary->entries;
        }
        // The order of glossary entries has no meaning and differs between database platforms.
        $actualEntries = $entriesByPair[$sourceLanguage . '-' . $targetLanguage] ?? [];
        $bySource = static fn (array $a, array $b): int => $a['source'] <=> $b['source'];
        usort($actualEntries, $bySource);
        usort($expectedEntries, $bySource);
        self::assertSame($expectedEntries, $actualEntries);
    }
}
