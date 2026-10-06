<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Domain\Repository;

use DeepL\MultilingualGlossaryDictionaryInfo;
use DeepL\MultilingualGlossaryInfo;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Cache\Backend\TaggableBackendInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use WebVision\Deepltranslate\Core\Domain\Dto\CurrentPage;
use WebVision\Deepltranslate\Core\Event\DeepLGlossaryIdEvent;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\EventListener\LocalGlossary;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * Every translated record and field asks for a glossary, so the glossary folders of a site are
 * resolved once per request. The glossaries in them are not cached, a glossary written in the
 * same process is used by the next lookup.
 */
final class GlossaryFolderRuntimeCacheTest extends AbstractDeepLTestCase
{
    use SiteBasedTestTrait;

    private const FOLDER_CACHE_TAG = 'deepltranslate_glossary_folders';

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
    public function glossaryFoldersAreCachedPerSite(): void
    {
        $subject = $this->get(GlossaryRepository::class);

        $firstSiteGlossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(3, 'First Content'));
        $secondSiteGlossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(11, 'Second Content'));

        self::assertSame('3f2b0000-0000-0000-0000-000000000001', $firstSiteGlossary->glossaryId);
        self::assertSame('', $secondSiteGlossary->glossaryId);
        self::assertSame(
            [
                'deepltranslate-glossary-folders-1' => [2],
                'deepltranslate-glossary-folders-10' => [],
            ],
            $this->getCachedGlossaryFolders()
        );
    }

    #[Test]
    public function glossaryFoldersOfASiteAreResolvedOnItsFirstLookup(): void
    {
        $subject = $this->get(GlossaryRepository::class);
        $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(3, 'First Content'));
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteLookup/secondSiteGlossaryFolder.csv');

        $glossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(11, 'Second Content'));

        self::assertSame('3f2b0000-0000-0000-0000-000000000004', $glossary->glossaryId);
    }

    /**
     * The folders of a site are resolved once per request, so a glossary folder added while the
     * request runs is used from the next request on.
     */
    #[Test]
    public function glossaryFolderAddedAfterTheFirstLookupIsUsedFromTheNextRequestOn(): void
    {
        $subject = $this->get(GlossaryRepository::class);
        $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(11, 'Second Content'));
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/SiteLookup/secondSiteGlossaryFolder.csv');

        $sameRequestGlossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(11, 'Second Content'));
        $this->getRuntimeCache()->flush();
        $nextRequestGlossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(11, 'Second Content'));

        self::assertSame('', $sameRequestGlossary->glossaryId);
        self::assertSame('3f2b0000-0000-0000-0000-000000000004', $nextRequestGlossary->glossaryId);
    }

    #[Test]
    public function pageOutsideAnySiteHasNoGlossaryAndCachesNothing(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/glossaryFolderOutsideAnySite.csv');
        $subject = $this->get(GlossaryRepository::class);

        $glossary = $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(21, 'Shared Glossary'));

        self::assertSame('', $glossary->glossaryId);
        self::assertFalse($glossary->ready);
        self::assertSame([], $this->getCachedGlossaryFolders());
    }

    #[Test]
    public function synchronisedGlossaryIsUsedByTheNextLookupOfTheSameRequest(): void
    {
        $subject = $this->get(GlossaryRepository::class);
        $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(3, 'First Content'));
        $subject->getGlossaryBySourceAndTarget('en', 'fr', new CurrentPage(3, 'First Content'));

        $subject->updateGlossaryRecord(
            new MultilingualGlossaryInfo(
                '3f2b0000-0000-0000-0000-000000000099',
                'Glossary: EN => DE',
                new \DateTime(),
                [
                    new MultilingualGlossaryDictionaryInfo('en', 'de', 1),
                    new MultilingualGlossaryDictionaryInfo('en', 'fr', 1),
                ]
            ),
            1,
            2
        );

        self::assertSame(
            '3f2b0000-0000-0000-0000-000000000099',
            $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(3, 'First Content'))->glossaryId
        );
        self::assertSame(
            '3f2b0000-0000-0000-0000-000000000099',
            $subject->getGlossaryBySourceAndTarget('en', 'fr', new CurrentPage(3, 'First Content'))->glossaryId
        );
    }

    #[Test]
    public function resetGlossaryIsNotUsedByTheNextLookupOfTheSameRequest(): void
    {
        $subject = $this->get(GlossaryRepository::class);
        $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(3, 'First Content'));

        $subject->resetGlossaryRecord(1);

        self::assertSame(
            '',
            $subject->getGlossaryBySourceAndTarget('en', 'de', new CurrentPage(3, 'First Content'))->glossaryId
        );
    }

    #[Test]
    public function droppedGlossaryIsNotPassedToTheNextTranslationOfTheSameRequest(): void
    {
        $listener = $this->get(LocalGlossary::class);
        $firstEvent = new DeepLGlossaryIdEvent('en', 'de', new CurrentPage(3, 'First Content'));
        $listener($firstEvent);

        $this->get(GlossaryRepository::class)->removeGlossarySync('3f2b0000-0000-0000-0000-000000000001');
        $secondEvent = new DeepLGlossaryIdEvent('en', 'de', new CurrentPage(3, 'First Content'));
        $listener($secondEvent);

        self::assertSame('3f2b0000-0000-0000-0000-000000000001', $firstEvent->glossaryId);
        self::assertSame('', $secondEvent->glossaryId);
    }

    /**
     * @return array<string, mixed>
     */
    private function getCachedGlossaryFolders(): array
    {
        $cache = $this->getRuntimeCache();
        $backend = $cache->getBackend();
        self::assertInstanceOf(TaggableBackendInterface::class, $backend);
        $folders = [];
        foreach ($backend->findIdentifiersByTag(self::FOLDER_CACHE_TAG) as $identifier) {
            $folders[$identifier] = $cache->get($identifier);
        }
        ksort($folders);

        return $folders;
    }

    private function getRuntimeCache(): FrontendInterface
    {
        return $this->get(CacheManager::class)->getCache('runtime');
    }
}
