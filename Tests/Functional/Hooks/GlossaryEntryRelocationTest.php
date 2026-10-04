<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * A term leaving or entering a synchronised folder changes the glossary of every folder involved.
 */
final class GlossaryEntryRelocationTest extends AbstractDeepLTestCase
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

        $this->importCSVDataSet(__DIR__ . '/Fixtures/twoSyncedGlossaryFolders.csv');
        $this->setUpBackendUser(1);
        // Moving a record resolves TCA labels through BackendUtility::getLanguageService().
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($GLOBALS['BE_USER']);
    }

    /**
     * @return \Generator<string, array{commandMap: array<string, mixed>, expectedResult: string}>
     */
    public static function termRelocations(): \Generator
    {
        yield 'term moved to another folder' => [
            'commandMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    1 => [
                        'move' => 4,
                    ],
                ],
            ],
            'expectedResult' => 'termMovedBetweenFolders.csv',
        ];
        yield 'term copied to another folder' => [
            'commandMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    1 => [
                        'copy' => 4,
                    ],
                ],
            ],
            'expectedResult' => 'termCopiedToFolder.csv',
        ];
    }

    /**
     * @param array<string, mixed> $commandMap
     */
    #[Test]
    #[DataProvider('termRelocations')]
    public function relocatedTermMarksAffectedGlossariesOutOfSync(array $commandMap, string $expectedResult): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $commandMap);

        $dataHandler->process_cmdmap();

        self::assertSame([], $dataHandler->errorLog);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Results/' . $expectedResult);
    }
}
