<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Upgrade;

use DeepL\MultilingualGlossaryDictionaryEntries;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Event\ModifyGlossaryNameEvent;
use WebVision\Deepltranslate\Glossary\Service\GlossaryNameService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;
use WebVision\Deepltranslate\Glossary\Tests\Functional\Client\Fixtures\CollectingLogger;
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

        // They describe glossaries removed at DeepL, the next synchronisation stores new ones.
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
    public function glossaryAlreadyRemovedAtDeeplIsNotReportedAsLeftBehind(): void
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        $glossaryId = $client->createGlossary('Removed before the migration', [
            new MultilingualGlossaryDictionaryEntries('en', 'de', ['tree' => 'Baum']),
        ])->glossaryId;
        $client->deleteGlossary($glossaryId);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/perPairGlossaryRemovedAtDeepl.csv');
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->update('tx_deepltranslate_glossary', ['glossary_id' => $glossaryId], ['uid' => 1]);
        $logger = new CollectingLogger();
        // Only the logger is replaced, to see what the wizard reports.
        $subject = new MigrateToMultilingualGlossaryWizard(
            $this->get(ConnectionPool::class),
            $client,
            $logger,
            $this->get(GlossaryNameService::class),
        );

        $subject->executeUpdate();

        // A warning names a glossary to delete by hand, which a removed one does not need.
        self::assertNotContains(LogLevel::WARNING, $logger->levels);
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
                    $queryBuilder->createNamedParameter($pageId, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)
                )
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
