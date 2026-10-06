<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Command\GlossaryCleanupCommand;
use WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class GlossaryCleanupCommandTest extends AbstractDeepLTestCase
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
        __DIR__ . '/../Fixtures/Extensions/test_services_override',
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

        $this->importCSVDataSet(__DIR__ . '/../Regression/Fixtures/glossary.csv');

        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);
        GeneralUtility::makeInstance(DeeplGlossaryService::class)
            ->syncGlossaries(2);
    }

    #[Test]
    public function cleanupOfUnsyncedGlossariesDetachesOnlyRecordsUnknownToDeepl(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/staleGlossary.csv');
        $syncedGlossaryId = $this->fetchGlossaryIdByPid(2);
        self::assertNotSame('', $syncedGlossaryId);
        $commandTester = new CommandTester($this->get(GlossaryCleanupCommand::class));
        $commandTester->setInputs(['yes']);

        $exitCode = $commandTester->execute(['--notinsync' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('', $this->fetchGlossaryIdByPid(1));
        // A glossary DeepL still knows is in sync and has to stay attached to its folder.
        self::assertSame($syncedGlossaryId, $this->fetchGlossaryIdByPid(2));
        self::assertCount(1, $this->get(DeeplGlossaryService::class)->listGlossaries());
    }

    /**
     * The glossary client returns an empty list when the request to DeepL fails.
     * An empty list must not make every record look stale.
     */
    #[Test]
    public function cleanupOfUnsyncedGlossariesDetachesNothingWhenDeeplListsNoGlossary(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/staleGlossary.csv');
        $syncedGlossaryId = $this->fetchGlossaryIdByPid(2);
        $glossaryService = $this->get(DeeplGlossaryService::class);
        // The mock server keeps the glossaries of earlier tests, remove every one of them.
        foreach ($glossaryService->listGlossaries() as $remoteGlossary) {
            $glossaryService->deleteGlossary($remoteGlossary->glossaryId);
        }
        self::assertSame([], $glossaryService->listGlossaries());
        $commandTester = new CommandTester($this->get(GlossaryCleanupCommand::class));
        $commandTester->setInputs(['yes']);

        $exitCode = $commandTester->execute(['--notinsync' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Nothing was detached', $commandTester->getDisplay());
        self::assertSame('3f2b0000-0000-0000-0000-00000000dead', $this->fetchGlossaryIdByPid(1));
        self::assertSame($syncedGlossaryId, $this->fetchGlossaryIdByPid(2));
    }

    private function fetchGlossaryIdByPid(int $pid): string
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        return (string)$queryBuilder
            ->select('glossary_id')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
    }
}
