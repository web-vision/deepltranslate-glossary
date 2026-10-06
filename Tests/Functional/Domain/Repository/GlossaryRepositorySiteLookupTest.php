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

    public static function unmarkedFolderOfTheSameSiteDataProvider(): \Generator
    {
        yield 'one folder not marked as glossary folder' => [
            'fixture' => __DIR__ . '/../../Fixtures/SiteLookup/secondSiteUnmarkedFolder.csv',
        ];
        yield 'two folders not marked as glossary folder' => [
            'fixture' => __DIR__ . '/../../Fixtures/SiteLookup/secondSiteTwoUnmarkedFolders.csv',
        ];
    }

    /**
     * Only a glossary folder can be synchronised, so the glossary of a folder not marked as
     * glossary folder could never be updated again and is not used.
     */
    #[DataProvider('unmarkedFolderOfTheSameSiteDataProvider')]
    #[Test]
    public function glossaryOfUnmarkedFolderOfTheSameSiteIsNotUsed(string $fixture): void
    {
        $this->importCSVDataSet($fixture);
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(11, 'Second Content'));

        self::assertSame(0, $glossary->uid);
        self::assertSame('', $glossary->glossaryId);
    }

    #[Test]
    public function glossaryFolderOfTheSiteIsUsedBesideUnmarkedFolder(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteLookup/secondSiteUnmarkedFolder.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteLookup/secondSiteGlossaryFolder.csv');
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(11, 'Second Content'));

        self::assertSame(4, $glossary->uid);
    }

    #[Test]
    public function glossaryWithLowestUidOfTheGlossaryFoldersOfTheSiteIsUsed(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteLookup/secondSiteTwoGlossaryFolders.csv');
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(11, 'Second Content'));

        self::assertSame(2, $glossary->uid);
        self::assertSame('3f2b0000-0000-0000-0000-000000000002', $glossary->glossaryId);
    }
}
