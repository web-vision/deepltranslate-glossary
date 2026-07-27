<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * Synchronising a glossary folder has to end up with exactly one persistent DeepL glossary
 * holding one dictionary per language pair, see the glossary API v3.
 */
final class MultilingualGlossarySyncTest extends AbstractDeepLTestCase
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

        $this->importCSVDataSet(__DIR__ . '/Fixtures/glossaryFolder.csv');

        // Resolving the localizations of a glossary folder goes through
        // TranslationConfigurationProvider, which requires an authenticated backend user.
        $this->setUpBackendUser(1);
    }

    #[Test]
    public function firstSyncCreatesOneGlossaryForTheFolder(): void
    {
        $subject = $this->get(MultilingualGlossaryService::class);

        $subject->syncGlossary(2);

        $glossaries = $this->fetchGlossaryRecords();
        self::assertCount(1, $glossaries, 'A folder maps to exactly one DeepL glossary.');
        self::assertNotSame('', $glossaries[0]['glossary_id']);
        self::assertSame(1, (int)$glossaries[0]['glossary_ready']);
        self::assertGreaterThan(0, (int)$glossaries[0]['glossary_lastsync']);
    }

    #[Test]
    public function firstSyncStoresDictionaryPerLanguagePair(): void
    {
        $subject = $this->get(MultilingualGlossaryService::class);

        $subject->syncGlossary(2);

        $dictionaries = $this->fetchDictionaryRecords();
        self::assertCount(1, $dictionaries);
        self::assertSame('en', $dictionaries[0]['source_lang']);
        self::assertSame('de', $dictionaries[0]['target_lang']);
        self::assertSame(2, (int)$dictionaries[0]['entry_count']);
        self::assertSame(1, (int)$dictionaries[0]['in_sync']);
    }

    #[Test]
    public function repeatedSyncKeepsTheSameGlossary(): void
    {
        $subject = $this->get(MultilingualGlossaryService::class);
        $subject->syncGlossary(2);
        $firstGlossaryId = $this->fetchGlossaryRecords()[0]['glossary_id'];

        $subject->syncGlossary(2);

        // A persistent glossary is edited in place, it must not be replaced on every sync.
        $glossaries = $this->fetchGlossaryRecords();
        self::assertCount(1, $glossaries);
        self::assertSame($firstGlossaryId, $glossaries[0]['glossary_id']);
    }

    #[Test]
    public function syncRecreatesGlossaryDeletedOnDeeplSide(): void
    {
        $subject = $this->get(MultilingualGlossaryService::class);
        $subject->syncGlossary(2);
        $firstGlossaryId = $this->fetchGlossaryRecords()[0]['glossary_id'];
        $this->get(GlossaryAPIV3ClientInterface::class)->deleteGlossary($firstGlossaryId);

        $subject->syncGlossary(2);

        $glossaries = $this->fetchGlossaryRecords();
        self::assertCount(1, $glossaries);
        self::assertNotSame('', $glossaries[0]['glossary_id']);
        self::assertNotSame($firstGlossaryId, $glossaries[0]['glossary_id']);
        self::assertSame(1, (int)$glossaries[0]['glossary_ready']);
    }

    #[Test]
    public function folderWithoutUsableEntriesDropsTheGlossary(): void
    {
        $subject = $this->get(MultilingualGlossaryService::class);
        $subject->syncGlossary(2);
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossaryentry')
            ->delete('tx_deepltranslate_glossaryentry', ['pid' => 2]);

        $subject->syncGlossary(2);

        // Without a single term pair there is nothing left to translate with, so the remote
        // glossary is removed instead of being left behind as an orphan.
        $glossaries = $this->fetchGlossaryRecords();
        self::assertCount(1, $glossaries);
        self::assertSame('', $glossaries[0]['glossary_id']);
        self::assertSame(0, (int)$glossaries[0]['glossary_ready']);
        self::assertSame([], $this->fetchDictionaryRecords());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchGlossaryRecords(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        return $queryBuilder
            ->select('uid', 'glossary_id', 'glossary_name', 'glossary_lastsync', 'glossary_ready')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter(2, Connection::PARAM_INT)
                )
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchDictionaryRecords(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossarydictionary');

        return $queryBuilder
            ->select('source_lang', 'target_lang', 'entry_count', 'in_sync')
            ->from('tx_deepltranslate_glossarydictionary')
            ->orderBy('source_lang')
            ->addOrderBy('target_lang')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
