<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;
use WebVision\Deepltranslate\Glossary\Upgrade\MigrateTablesFromOldStructureWizard;

final class MigrateTablesFromOldStructureWizardTest extends AbstractDeepLTestCase
{
    protected function setUp(): void
    {
        $this->testExtensionsToLoad[] = 'web-vision/test-migration';
        parent::setUp();
    }

    #[Test]
    public function notDeletedTableMigrationWorks(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/migration.csv');
        $subject = $this->get(MigrateTablesFromOldStructureWizard::class);
        $necessary = $subject->updateNecessary();
        self::assertTrue($necessary);
        $updateDone = $subject->executeUpdate();
        self::assertTrue($updateDone);
        self::assertCSVDataSet(__DIR__ . '/Fixtures/Result/standardMigration.csv');
    }
}
