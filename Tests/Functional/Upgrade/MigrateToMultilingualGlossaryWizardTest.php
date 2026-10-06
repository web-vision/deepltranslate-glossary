<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Upgrade;

use DeepL\DeepLException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use WebVision\Deepltranslate\Core\Exception\ApiKeyNotSetException;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Event\ModifyGlossaryNameEvent;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;
use WebVision\Deepltranslate\Glossary\Upgrade\LegacyGlossaryIdStore;
use WebVision\Deepltranslate\Glossary\Upgrade\MigrateToMultilingualGlossaryWizard;

/**
 * The glossary API v2 stored one glossary per language pair. Those records have to collapse
 * into the single glossary record per folder the API v3 works with.
 */
final class MigrateToMultilingualGlossaryWizardTest extends AbstractDeepLTestCase
{
    #[Test]
    public function migrationIsNecessaryForPerPairRecords(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        self::assertTrue($subject->updateNecessary());
    }

    #[Test]
    public function everyFolderKeepsExactlyOneGlossaryRecord(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        self::assertTrue($subject->executeUpdate());

        self::assertCount(1, $this->fetchGlossaryRecords(2));
        self::assertCount(1, $this->fetchGlossaryRecords(5));
    }

    #[Test]
    public function migratedRecordIsDetachedFromItsFormerGlossary(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        $subject->executeUpdate();

        // The glossary ids belong to glossaries created through the API v2, so the record must
        // not keep them. The next synchronisation publishes the folder through the API v3.
        $glossary = $this->fetchGlossaryRecords(2)[0];
        self::assertSame('', $glossary['glossary_id']);
        self::assertSame(0, (int)$glossary['glossary_ready']);
        self::assertSame(0, (int)$glossary['glossary_lastsync']);
        self::assertSame('', $glossary['source_lang']);
        self::assertSame('', $glossary['target_lang']);
    }

    #[Test]
    public function dictionariesOfTheFormerGlossariesAreRemoved(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/dictionariesOfMigratedGlossaries.csv');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        $subject->executeUpdate();

        // They describe the glossaries left behind, the next synchronisation stores new ones.
        self::assertSame(
            0,
            $this->get(ConnectionPool::class)
                ->getConnectionForTable('tx_deepltranslate_glossarydictionary')
                ->count('uid', 'tx_deepltranslate_glossarydictionary', [])
        );
    }

    #[Test]
    public function migratedRecordIsNamedAfterItsFolder(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        $subject->executeUpdate();

        // The former names describe a single language pair, the next synchronisation publishes
        // the glossary under the name of its record.
        self::assertSame('Glossary [2]', $this->fetchGlossaryRecords(2)[0]['glossary_name']);
        self::assertSame('Second Glossary [5]', $this->fetchGlossaryRecords(5)[0]['glossary_name']);
    }

    #[Test]
    public function migratedRecordIsNamedByANameListener(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        /** @var Container $container */
        $container = $this->getContainer();
        $container->set('glossary-name-listener', static function (ModifyGlossaryNameEvent $event): void {
            $event->glossaryName = sprintf('ACME %d', $event->pageId);
        });
        $this->get(ListenerProvider::class)->addListener(ModifyGlossaryNameEvent::class, 'glossary-name-listener');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        $subject->executeUpdate();

        self::assertSame('ACME 2', $this->fetchGlossaryRecords(2)[0]['glossary_name']);
    }

    #[Test]
    public function glossariesOfApiV2AreKeptAtDeepl(): void
    {
        // A copy of the database sharing the API key, a staging system for example, must not
        // delete the glossaries the live system still translates with.
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        $firstGlossaryId = $this->createRemoteGlossary('Glossary: en => de');
        $secondGlossaryId = $this->createRemoteGlossary('Second: de => en');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        $this->setGlossaryId(1, $firstGlossaryId);
        $this->setGlossaryId(4, $secondGlossaryId);
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        self::assertTrue($subject->executeUpdate());

        self::assertSame($firstGlossaryId, $client->getGlossary($firstGlossaryId)->glossaryId);
        self::assertSame($secondGlossaryId, $client->getGlossary($secondGlossaryId)->glossaryId);
        self::assertEqualsCanonicalizing(
            [$firstGlossaryId, 'v2-en-fr', $secondGlossaryId],
            $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds()
        );
    }

    #[Test]
    public function glossaryIdsLeftBehindAreStored(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        $subject->executeUpdate();

        // A record without glossary id has nothing left at DeepL.
        self::assertEqualsCanonicalizing(
            ['v2-en-de', 'v2-en-fr', 'v2-de-en'],
            $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds()
        );
    }

    #[Test]
    public function glossaryIdsLeftBehindAreReportedWithTheCommandsToRemoveThem(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        $output = new BufferedOutput();
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $display = $output->fetch();
        foreach (['v2-en-de', 'v2-en-fr', 'v2-de-en'] as $glossaryId) {
            self::assertStringContainsString('deepl:glossary:cleanup --glossaryId ' . $glossaryId, $display);
        }
        self::assertStringContainsString('deepl:glossary:cleanup --legacy', $display);
    }

    #[Test]
    public function migrationNeedsNoConnectionToDeepl(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['deepltranslate_core']['apiKey'] = '';
        try {
            $this->get(GlossaryAPIV3ClientInterface::class)->getAllGlossaries();
            self::fail('DeepL has to be unusable without an API key for this test.');
        } catch (DeepLException|ApiKeyNotSetException) {
        }
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        self::assertTrue($subject->executeUpdate());

        self::assertCount(1, $this->fetchGlossaryRecords(2));
        self::assertCount(1, $this->fetchGlossaryRecords(5));
        self::assertCount(3, $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds());
    }

    #[Test]
    public function rerunMigratesOnlyRecordsOfApiV2CreatedSinceAndKeepsTheStoredIds(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);
        $subject->executeUpdate();
        $migratedRecord = $this->fetchGlossaryRecords(2)[0];
        // The deprecated API v2 handling may still create such a record after the migration.
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->insert('tx_deepltranslate_glossary', [
                'pid' => 2,
                'glossary_id' => 'v2-en-nl',
                'glossary_name' => 'Glossary: en => nl',
                'glossary_ready' => 1,
                'source_lang' => 'en',
                'target_lang' => 'nl',
            ]);

        self::assertTrue($subject->updateNecessary());
        self::assertTrue($subject->executeUpdate());

        self::assertSame([$migratedRecord], $this->fetchGlossaryRecords(2));
        self::assertEqualsCanonicalizing(
            ['v2-en-de', 'v2-en-fr', 'v2-de-en', 'v2-en-nl'],
            $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds()
        );
        self::assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function folderWithRecordOfApiV3KeepsItsGlossary(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/mixedGlossaryFolder.csv');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        $subject->executeUpdate();

        $glossaries = $this->fetchGlossaryRecords(2);
        self::assertCount(1, $glossaries);
        self::assertSame(2, (int)$glossaries[0]['uid']);
        self::assertSame('v3-live', $glossaries[0]['glossary_id']);
        self::assertSame(1, (int)$glossaries[0]['glossary_ready']);
        self::assertSame(1800000000, (int)$glossaries[0]['glossary_lastsync']);
        self::assertSame('Glossary [2]', $glossaries[0]['glossary_name']);
        self::assertSame([2, 3], $this->fetchDictionaryUids());
        // The glossary of the API v3 is still in use, even when a record of the API v2 named it.
        self::assertEqualsCanonicalizing(
            ['v2-en-de', 'v2-en-fr'],
            $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds()
        );
    }

    #[Test]
    public function recordWithoutGlossaryIdOfReleasedVersionsIsMigrated(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        // Released versions left the column nullable, so a record may hold NULL instead of ''.
        $this->setGlossaryId(1, null);
        $this->setGlossaryId(4, null);
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        self::assertTrue($subject->executeUpdate());

        self::assertSame('', $this->fetchGlossaryRecords(2)[0]['glossary_id']);
        self::assertSame('', $this->fetchGlossaryRecords(5)[0]['glossary_id']);
        self::assertSame(['v2-en-fr'], $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds());
    }

    #[Test]
    public function recordsOfADeletedFolderAreMigrated(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossariesOfDeletedFolder.csv');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        self::assertTrue($subject->updateNecessary());
        self::assertTrue($subject->executeUpdate());

        $glossaries = $this->fetchGlossaryRecords(2);
        self::assertCount(1, $glossaries);
        // The title of a deleted folder is not used.
        self::assertSame('Glossary [2]', $glossaries[0]['glossary_name']);
        self::assertEqualsCanonicalizing(
            ['v2-en-de', 'v2-en-fr'],
            $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds()
        );
        self::assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function migrationIsNotRepeated(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaries.csv');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);
        $subject->executeUpdate();

        self::assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function migrationIsNotNecessaryWithoutAnyGlossary(): void
    {
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        self::assertFalse($subject->updateNecessary());
    }

    private function createRemoteGlossary(string $name): string
    {
        return $this->get(GlossaryAPIV3ClientInterface::class)->createGlossary($name, [
            new MultilingualGlossaryDictionaryEntries('en', 'de', ['tree' => 'Baum']),
        ])->glossaryId;
    }

    private function setGlossaryId(int $uid, ?string $glossaryId): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->update('tx_deepltranslate_glossary', ['glossary_id' => $glossaryId], ['uid' => $uid]);
    }

    /**
     * @return list<int>
     */
    private function fetchDictionaryUids(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossarydictionary');

        return array_map('intval', $queryBuilder
            ->select('uid')
            ->from('tx_deepltranslate_glossarydictionary')
            ->orderBy('uid')
            ->executeQuery()
            ->fetchFirstColumn());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchGlossaryRecords(int $pageId): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        return $queryBuilder
            ->select('uid', 'glossary_id', 'glossary_name', 'glossary_lastsync', 'glossary_ready', 'source_lang', 'target_lang')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                )
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
