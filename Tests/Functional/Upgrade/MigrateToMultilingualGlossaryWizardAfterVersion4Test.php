<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Registry;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;
use WebVision\Deepltranslate\Glossary\Upgrade\MigrateTablesFromOldStructureWizard;
use WebVision\Deepltranslate\Glossary\Upgrade\MigrateToMultilingualGlossaryWizard;

/**
 * An upgrade from deepltranslate 4.x copies the glossaries of the former tables first. The core
 * marks every wizard done which is not necessary before the wizards of a run are executed, so the
 * migration has to stay necessary until that copy happened.
 */
final class MigrateToMultilingualGlossaryWizardAfterVersion4Test extends AbstractDeepLTestCase
{
    protected function setUp(): void
    {
        $this->testExtensionsToLoad[] = 'web-vision/test-migration';
        parent::setUp();
    }

    #[Test]
    public function migrationWaitsForTheTablesOfVersion4(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/migration.csv');
        $output = new BufferedOutput();
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);
        $subject->setOutput($output);

        self::assertTrue($subject->updateNecessary());
        self::assertFalse($subject->executeUpdate());

        self::assertStringContainsString('deepltranslateGlossary_migrateGlossaryTables', $output->fetch());
        self::assertSame(0, $this->countGlossaryRecords());
    }

    #[Test]
    public function migrationCollapsesTheRecordsCopiedFromVersion4(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/migration.csv');
        $oldStructureWizard = $this->get(MigrateTablesFromOldStructureWizard::class);
        self::assertTrue($oldStructureWizard->executeUpdate());
        $this->get(Registry::class)->set('installUpdate', MigrateTablesFromOldStructureWizard::class, 1);
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        self::assertTrue($subject->updateNecessary());
        self::assertTrue($subject->executeUpdate());

        self::assertSame(1, $this->countGlossaryRecords());
        self::assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function tablesOfVersion4AreNotWaitedForOnceTheirWizardIsDone(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/migration.csv');
        $this->get(Registry::class)->set('installUpdate', MigrateTablesFromOldStructureWizard::class, 1);
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        self::assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function tablesOfVersion4AreNotWaitedForWhenTheyCanNoLongerBeCopied(): void
    {
        // The tables of version 4 are only copied into empty tables, so they never will be here.
        $this->importCSVDataSet(__DIR__ . '/Fixtures/migration.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/glossaryOfCurrentTables.csv');
        $subject = $this->get(MigrateToMultilingualGlossaryWizard::class);

        self::assertFalse($subject->updateNecessary());
        self::assertTrue($subject->executeUpdate());
    }

    private function countGlossaryRecords(): int
    {
        return $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->count('uid', 'tx_deepltranslate_glossary', []);
    }
}
