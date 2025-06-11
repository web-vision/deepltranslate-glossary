<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\TestCase\FunctionalTestCase;
use WebVision\Deepltranslate\Glossary\Upgrade\MigrateTablesFromOldStructureWizard;

final class MigrateTablesFromOldStructureWizardTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'web-vision/deeplcom-deepl-php',
        'web-vision/deepl-base',
        'web-vision/deepltranslate-core',
        'web-vision/deepltranslate-glossary',
        __DIR__ . '/Fixtures/Extensions/test_migration',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
        'typo3/cms-scheduler',
        'typo3/cms-setup',
    ];

    protected function setUp(): void
    {
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
