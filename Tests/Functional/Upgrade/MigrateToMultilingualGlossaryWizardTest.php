<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;
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
