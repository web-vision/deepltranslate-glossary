<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Domain\Repository;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use WebVision\Deepltranslate\Core\Domain\Dto\CurrentPage;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * A glossary is valid for every language pair its dictionaries cover, so resolving a pair has to
 * go through the dictionaries instead of the glossary record itself.
 */
final class GlossaryLookupTest extends AbstractDeepLTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
            'iso' => 'en',
            'hrefLang' => 'en-US',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => '',
            ],
        ],
        'DE' => [
            'id' => 1,
            'title' => 'Deutsch',
            'locale' => 'de_DE',
            'iso' => 'de',
            'hrefLang' => 'de-DE',
            'direction' => '',
            'custom' => [
                'deeplTargetLanguage' => 'DE',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeSiteConfiguration(
            identifier: 'acme',
            site: $this->buildSiteConfiguration(rootPageId: 1),
            languages: [
                $this->buildDefaultLanguageConfiguration('EN', '/'),
                $this->buildLanguageConfiguration('DE', '/de/', ['EN'], 'strict'),
            ],
        );

        $this->importCSVDataSet(__DIR__ . '/Fixtures/syncedGlossary.csv');
    }

    /**
     * @return \Generator<string, array{sourceLanguage: string, targetLanguage: string}>
     */
    public static function coveredLanguagePairs(): \Generator
    {
        yield 'first dictionary of the glossary' => [
            'sourceLanguage' => 'en',
            'targetLanguage' => 'de',
        ];
        yield 'second dictionary of the same glossary' => [
            'sourceLanguage' => 'en',
            'targetLanguage' => 'fr',
        ];
        yield 'language code is matched case insensitively' => [
            'sourceLanguage' => 'EN',
            'targetLanguage' => 'DE',
        ];
        yield 'regional target falls back to its base language' => [
            'sourceLanguage' => 'en',
            'targetLanguage' => 'de-AT',
        ];
    }

    #[Test]
    #[DataProvider('coveredLanguagePairs')]
    public function glossaryIsResolvedForEveryCoveredPair(string $sourceLanguage, string $targetLanguage): void
    {
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget(
            $sourceLanguage,
            $targetLanguage,
            new CurrentPage(2, 'Glossary')
        );

        self::assertSame('3f2b0000-0000-0000-0000-000000000001', $glossary->glossaryId);
        self::assertTrue($glossary->ready);
    }

    #[Test]
    public function pairWithoutDictionaryResolvesNoGlossary(): void
    {
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget(
            'en',
            'it',
            new CurrentPage(2, 'Glossary')
        );

        self::assertSame('', $glossary->glossaryId);
        self::assertFalse($glossary->ready);
    }
}
