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
 * A page is translated with a glossary of its own site only, never with the glossary of another site.
 */
final class GlossaryRepositorySiteLookupTest extends AbstractDeepLTestCase
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

    protected array $testExtensionsToLoad = [
        'web-vision/deeplcom-deepl-php',
        'web-vision/deepl-base',
        'web-vision/deepltranslate-core',
        'web-vision/deepltranslate-glossary',
        __DIR__ . '/../../Fixtures/Extensions/test_services_override',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeSiteConfiguration(
            identifier: 'first',
            site: $this->buildSiteConfiguration(rootPageId: 1, base: 'https://first.example/'),
            languages: [
                $this->buildDefaultLanguageConfiguration('EN', '/'),
                $this->buildLanguageConfiguration('DE', '/de/', ['EN'], 'strict'),
            ],
        );
        $this->writeSiteConfiguration(
            identifier: 'second',
            site: $this->buildSiteConfiguration(rootPageId: 10, base: 'https://second.example/'),
            languages: [
                $this->buildDefaultLanguageConfiguration('EN', '/'),
                $this->buildLanguageConfiguration('DE', '/de/', ['EN'], 'strict'),
            ],
        );

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteLookup/twoSites.csv');
    }

    #[Test]
    public function glossaryOfTheGlossaryFolderOfTheSiteIsUsedForContentPage(): void
    {
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(3, 'First Content'));

        self::assertSame(1, $glossary->uid);
        self::assertSame('3f2b0000-0000-0000-0000-000000000001', $glossary->glossaryId);
    }

    #[Test]
    public function glossaryOfAnotherSiteIsNotUsed(): void
    {
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(11, 'Second Content'));

        self::assertSame(0, $glossary->uid);
        self::assertSame('', $glossary->glossaryId);
        self::assertFalse($glossary->ready);
    }

    #[Test]
    public function glossaryOfUnmarkedFolderOfTheSameSiteIsUsed(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteLookup/secondSiteUnmarkedFolder.csv');
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(11, 'Second Content'));

        self::assertSame(2, $glossary->uid);
        self::assertSame('3f2b0000-0000-0000-0000-000000000002', $glossary->glossaryId);
    }

    #[Test]
    public function glossaryFolderOfTheSiteTakesPrecedenceOverUnmarkedFolder(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteLookup/secondSiteUnmarkedFolder.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteLookup/secondSiteGlossaryFolder.csv');
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(11, 'Second Content'));

        self::assertSame(4, $glossary->uid);
    }

    public static function glossaryWithLowestUidOfTheSiteIsUsedDataProvider(): \Generator
    {
        yield 'two glossary folders' => [
            'fixture' => __DIR__ . '/../../Fixtures/SiteLookup/secondSiteTwoGlossaryFolders.csv',
        ];
        yield 'two folders not marked as glossary folder' => [
            'fixture' => __DIR__ . '/../../Fixtures/SiteLookup/secondSiteTwoUnmarkedFolders.csv',
        ];
    }

    #[DataProvider('glossaryWithLowestUidOfTheSiteIsUsedDataProvider')]
    #[Test]
    public function glossaryWithLowestUidOfTheSiteIsUsed(string $fixture): void
    {
        $this->importCSVDataSet($fixture);
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(11, 'Second Content'));

        self::assertSame(2, $glossary->uid);
        self::assertSame('3f2b0000-0000-0000-0000-000000000002', $glossary->glossaryId);
    }

    public static function glossaryFolderOutsideAnySiteDoesNotPreventTheLookupDataProvider(): \Generator
    {
        yield 'glossary folder in a storage folder outside any site' => [
            'fixture' => __DIR__ . '/../../Fixtures/SiteLookup/glossaryFolderOutsideAnySite.csv',
        ];
        yield 'glossary folder at the root level' => [
            'fixture' => __DIR__ . '/../../Fixtures/SiteLookup/glossaryFolderAtRootLevel.csv',
        ];
    }

    #[DataProvider('glossaryFolderOutsideAnySiteDoesNotPreventTheLookupDataProvider')]
    #[Test]
    public function glossaryFolderOutsideAnySiteDoesNotPreventTheLookup(string $fixture): void
    {
        $this->importCSVDataSet($fixture);
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(3, 'First Content'));

        self::assertSame(1, $glossary->uid);
        self::assertSame('3f2b0000-0000-0000-0000-000000000001', $glossary->glossaryId);
    }

    #[Test]
    public function pageOutsideAnySiteGetsNoGlossary(): void
    {
        // The glossary folders outside any site belong to no site, not to the one of the page.
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteLookup/glossaryFolderOutsideAnySite.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteLookup/glossaryFolderAtRootLevel.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteLookup/pageOutsideAnySite.csv');
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(31, 'Without Site Content'));

        self::assertSame(0, $glossary->uid);
        self::assertSame('', $glossary->glossaryId);
        self::assertFalse($glossary->ready);
    }
}
