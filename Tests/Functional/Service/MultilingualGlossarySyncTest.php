<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Service;

use DeepL\MultilingualGlossaryDictionaryEntries;
use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use WebVision\Deepltranslate\Core\Client\DeepLClientFactoryInterface;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Event\ModifyGlossaryNameEvent;
use WebVision\Deepltranslate\Glossary\Exception\GlossaryFolderNotSyncableException;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;
use WebVision\Deepltranslate\Glossary\Tests\Functional\Service\Fixtures\ConcurrentlyDeletingGlossaryClient;

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
    public function repeatedSyncRecordsTheTimeOfTheSync(): void
    {
        $subject = $this->get(MultilingualGlossaryService::class);
        $subject->syncGlossary(2);
        $this->get(Context::class)->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('@2000000000')));

        $subject->syncGlossary(2);

        // The glossary keeps its creation time at DeepL, the record has to tell the last sync.
        self::assertSame(2000000000, (int)$this->fetchGlossaryRecords()[0]['glossary_lastsync']);
    }

    #[Test]
    public function repeatedSyncStoresTheCurrentEntryCount(): void
    {
        $subject = $this->get(MultilingualGlossaryService::class);
        $subject->syncGlossary(2);
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossaryentry')
            ->delete('tx_deepltranslate_glossaryentry', ['uid' => 4]);

        $subject->syncGlossary(2);

        $dictionaries = $this->fetchDictionaryRecords();
        self::assertCount(1, $dictionaries);
        self::assertSame(1, (int)$dictionaries[0]['entry_count']);
    }

    #[Test]
    public function dictionaryRemovedDuringSyncKeepsTheGlossary(): void
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        $this->get(MultilingualGlossaryService::class)->syncGlossary(2);
        $glossaryId = $this->fetchGlossaryRecords()[0]['glossary_id'];
        $client->replaceDictionary(
            $glossaryId,
            new MultilingualGlossaryDictionaryEntries('en', 'fr', ['proton beam' => 'faisceau de protons'])
        );
        // Only the client is replaced, to remove the obsolete dictionary right before the sync
        // does, as a concurrent synchronisation would.
        $subject = new MultilingualGlossaryService(
            $this->get(CacheManager::class)->getCache('deepltranslate_glossary'),
            new ConcurrentlyDeletingGlossaryClient(new NullLogger(), $this->get(DeepLClientFactoryInterface::class)),
            $this->get(GlossaryRepository::class),
            $this->get(SiteFinder::class),
            $this->get(LockFactory::class),
        );

        $subject->syncGlossary(2);

        $glossaries = $this->fetchGlossaryRecords();
        self::assertCount(1, $glossaries);
        self::assertSame($glossaryId, $glossaries[0]['glossary_id']);
        self::assertSame(1, (int)$glossaries[0]['glossary_ready']);
        self::assertCount(1, $this->fetchDictionaryRecords());
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
    public function folderBeingSynchronisedIsNotSynchronisedAgainMeanwhile(): void
    {
        // Held by a concurrent synchronisation, for example the scheduler while an editor clicks.
        $lock = $this->get(LockFactory::class)->createLocker(
            'deepltranslate_glossary_sync_2',
            LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK
        );
        $lock->acquire(LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK);
        $subject = $this->get(MultilingualGlossaryService::class);

        try {
            $subject->syncGlossary(2);
            self::fail('A folder being synchronised must not be synchronised a second time at once.');
        } catch (GlossaryFolderNotSyncableException $exception) {
            self::assertSame(1791134376, $exception->getCode());
        } finally {
            $lock->release();
        }

        self::assertSame([], $this->fetchGlossaryRecords());
    }

    #[Test]
    public function glossaryCreatedAtDeeplIsRemovedWhenItCannotBeStored(): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_deepltranslate_glossarydictionary');
        // Makes storing the dictionaries fail after DeepL created the glossary.
        $connection->executeStatement('ALTER TABLE tx_deepltranslate_glossarydictionary RENAME TO tx_deepltranslate_glossarydictionary_away');
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        // The mock server shares its glossaries between tests.
        $numberOfGlossaries = count($client->getAllGlossaries());
        $subject = $this->get(MultilingualGlossaryService::class);

        try {
            $subject->syncGlossary(2);
            self::fail('A failure to store the synchronised glossary has to be reported.');
        } catch (DBALException) {
        } finally {
            $connection->executeStatement('ALTER TABLE tx_deepltranslate_glossarydictionary_away RENAME TO tx_deepltranslate_glossarydictionary');
        }

        // Without its id stored, the next synchronisation would create the glossary once more.
        self::assertCount($numberOfGlossaries, $client->getAllGlossaries());
        self::assertSame('', (string)($this->fetchGlossaryRecords()[0]['glossary_id'] ?? ''));
    }

    #[Test]
    public function recordWithoutGlossaryIdOfReleasedVersionsIsSynced(): void
    {
        // Released versions left the column nullable, so a record may hold NULL instead of ''.
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->insert('tx_deepltranslate_glossary', [
                'pid' => 2,
                'glossary_id' => null,
                'glossary_name' => 'Glossary',
            ]);
        $subject = $this->get(MultilingualGlossaryService::class);

        $subject->syncGlossary(2);

        $glossaries = $this->fetchGlossaryRecords();
        self::assertCount(1, $glossaries);
        self::assertNotSame('', (string)$glossaries[0]['glossary_id']);
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

    #[Test]
    public function folderWithOnlyBlankTranslationsDropsTheGlossary(): void
    {
        $subject = $this->get(MultilingualGlossaryService::class);
        $subject->syncGlossary(2);
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossaryentry')
            ->update('tx_deepltranslate_glossaryentry', ['term' => '   '], ['sys_language_uid' => 1]);

        $subject->syncGlossary(2);

        // Cleaning leaves no term pair, and DeepL refuses a dictionary without entries.
        $glossaries = $this->fetchGlossaryRecords();
        self::assertCount(1, $glossaries);
        self::assertSame('', $glossaries[0]['glossary_id']);
        self::assertSame([], $this->fetchDictionaryRecords());
    }

    #[Test]
    public function cachedEmptyLanguagePairsAreFetchedAgain(): void
    {
        $cache = $this->get(CacheManager::class)->getCache('deepltranslate_glossary');
        $cache->set('wv-deepl-glossary-pairs', []);
        $subject = $this->get(MultilingualGlossaryService::class);

        $subject->syncGlossary(2);

        self::assertNotSame('', $this->fetchGlossaryRecords()[0]['glossary_id'] ?? '');
        self::assertNotSame([], $cache->get('wv-deepl-glossary-pairs'));
    }

    #[Test]
    public function folderWithoutEntriesGetsNoGlossaryRecord(): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossaryentry')
            ->delete('tx_deepltranslate_glossaryentry', ['pid' => 2]);
        $subject = $this->get(MultilingualGlossaryService::class);

        $subject->syncGlossary(2);

        self::assertSame([], $this->fetchGlossaryRecords());
    }

    #[Test]
    public function hiddenGlossaryFolderIsRejected(): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('pages')
            ->update('pages', ['hidden' => 1], ['uid' => 2]);
        $subject = $this->get(MultilingualGlossaryService::class);

        try {
            $subject->syncGlossary(2);
            self::fail('Synchronising a hidden glossary folder has to be rejected.');
        } catch (GlossaryFolderNotSyncableException $exception) {
            self::assertSame(1791123486, $exception->getCode());
        }
        self::assertSame([], $this->fetchGlossaryRecords());
    }

    #[Test]
    public function translationOfAGlossaryFolderIsRejected(): void
    {
        $subject = $this->get(MultilingualGlossaryService::class);

        // The terms of every language live on the folder in the default language, page 2.
        try {
            $subject->syncGlossary(3);
            self::fail('Synchronising the translation of a glossary folder has to be rejected.');
        } catch (GlossaryFolderNotSyncableException $exception) {
            self::assertSame(1791123486, $exception->getCode());
        }
        self::assertSame(0, $this->countGlossaryRecordsOnPage(3));
    }

    #[Test]
    public function folderNotConfiguredAsGlossaryIsRejected(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/plainSysfolderWithEntries.csv');
        $subject = $this->get(MultilingualGlossaryService::class);

        try {
            $subject->syncGlossary(5);
            self::fail('Synchronising a folder not set up as glossary has to be rejected.');
        } catch (GlossaryFolderNotSyncableException $exception) {
            self::assertSame(1791123486, $exception->getCode());
        }
        self::assertSame(0, $this->countGlossaryRecordsOnPage(5));
    }

    #[Test]
    public function glossaryFolderOutsideAnySiteIsRejected(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/glossaryFolderOutsideAnySite.csv');
        $subject = $this->get(MultilingualGlossaryService::class);

        try {
            $subject->syncGlossary(21);
            self::fail('Synchronising a glossary folder outside any site has to be rejected.');
        } catch (GlossaryFolderNotSyncableException $exception) {
            self::assertSame(1791123489, $exception->getCode());
        }
        self::assertSame(0, $this->countGlossaryRecordsOnPage(21));
    }

    #[Test]
    public function translationInLanguageRemovedFromTheSiteIsSkipped(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/translationInRemovedLanguage.csv');
        $subject = $this->get(MultilingualGlossaryService::class);

        $subject->syncGlossary(2);

        $dictionaries = $this->fetchDictionaryRecords();
        self::assertCount(1, $dictionaries);
        self::assertSame('de', $dictionaries[0]['target_lang']);
    }

    /**
     * @return \Generator<string, array{sourceTerm: string, targetTerm: string, expectedEntries: array<string, string>}>
     */
    public static function uncleanTermPairs(): \Generator
    {
        yield 'surrounding whitespace is trimmed' => [
            'sourceTerm' => '  proton beam  ',
            'targetTerm' => "\tProtonenstrahl\n",
            'expectedEntries' => [
                'glossary term' => 'Glossareintrag',
                'proton beam' => 'Protonenstrahl',
            ],
        ];
        yield 'surrounding Unicode spaces are trimmed' => [
            'sourceTerm' => "\u{3000}proton beam\u{00A0}",
            'targetTerm' => "\u{2009}Protonenstrahl\u{202F}",
            'expectedEntries' => [
                'glossary term' => 'Glossareintrag',
                'proton beam' => 'Protonenstrahl',
            ],
        ];
        yield 'pair with whitespace only source is dropped' => [
            'sourceTerm' => '      ',
            'targetTerm' => 'Protonenstrahl',
            'expectedEntries' => [
                'glossary term' => 'Glossareintrag',
            ],
        ];
        yield 'pair with whitespace only target is dropped' => [
            'sourceTerm' => 'proton beam',
            'targetTerm' => '   ',
            'expectedEntries' => [
                'glossary term' => 'Glossareintrag',
            ],
        ];
        yield 'control characters inside a term become a single space' => [
            'sourceTerm' => "proton\tbeam",
            'targetTerm' => "Protonen\u{0085}\u{2028}strahl",
            'expectedEntries' => [
                'glossary term' => 'Glossareintrag',
                'proton beam' => 'Protonen strahl',
            ],
        ];
        yield 'pair with control characters only is dropped' => [
            'sourceTerm' => "\u{2029}\x07",
            'targetTerm' => 'Protonenstrahl',
            'expectedEntries' => [
                'glossary term' => 'Glossareintrag',
            ],
        ];
    }

    /**
     * A single term left unfilled by an editor must not abort the synchronisation of a whole
     * glossary folder, so unusable pairs are dropped instead of rejected.
     *
     * @param array<string, string> $expectedEntries
     */
    #[Test]
    #[DataProvider('uncleanTermPairs')]
    public function uncleanTermsAreSynchronisedCleaned(string $sourceTerm, string $targetTerm, array $expectedEntries): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_deepltranslate_glossaryentry');
        $connection->update('tx_deepltranslate_glossaryentry', ['term' => $sourceTerm], ['uid' => 3]);
        $connection->update('tx_deepltranslate_glossaryentry', ['term' => $targetTerm], ['uid' => 4]);
        $subject = $this->get(MultilingualGlossaryService::class);

        $subject->syncGlossary(2);

        $glossaryId = $this->fetchGlossaryRecords()[0]['glossary_id'];
        $entries = $this->get(GlossaryAPIV3ClientInterface::class)->getGlossaryEntries($glossaryId, 'en', 'de')[0]->entries;
        self::assertEquals($expectedEntries, $entries);
    }

    #[Test]
    public function termsDifferingInWhitespaceOnlyKeepTheOldestPair(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/duplicateTerms.csv');
        $subject = $this->get(MultilingualGlossaryService::class);

        $subject->syncGlossary(2);

        $glossaryId = $this->fetchGlossaryRecords()[0]['glossary_id'];
        $entries = $this->get(GlossaryAPIV3ClientInterface::class)->getGlossaryEntries($glossaryId, 'en', 'de')[0]->entries;
        self::assertSame('erster Begriff', $entries['duplicate term'] ?? null);
    }

    #[Test]
    public function glossaryIsCreatedUnderTheNameOfANameListener(): void
    {
        /** @var Container $container */
        $container = $this->getContainer();
        $container->set('glossary-name-listener', static function (ModifyGlossaryNameEvent $event): void {
            $event->glossaryName = 'ACME glossary';
        });
        $this->get(ListenerProvider::class)->addListener(ModifyGlossaryNameEvent::class, 'glossary-name-listener');
        $subject = $this->get(MultilingualGlossaryService::class);

        $subject->syncGlossary(2);

        $glossary = $this->fetchGlossaryRecords()[0];
        self::assertSame('ACME glossary', $glossary['glossary_name']);
        self::assertSame('ACME glossary', $this->get(GlossaryAPIV3ClientInterface::class)->getGlossary($glossary['glossary_id'])->name);
    }

    private function countGlossaryRecordsOnPage(int $pageId): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        return (int)$queryBuilder
            ->count('uid')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
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
