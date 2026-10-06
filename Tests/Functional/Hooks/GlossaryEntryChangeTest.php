<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Hooks\UpdatedGlossaryEntryTermHook;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * Every change to the terms of a synchronised folder leaves its DeepL glossaries behind until the
 * next synchronisation.
 */
final class GlossaryEntryChangeTest extends AbstractDeepLTestCase
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
            'locale' => 'de_DE.UTF-8',
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
     * @return \Generator<string, array{dataMap: array<string, mixed>, commandMap: array<string, mixed>, expectedResult: string}>
     */
    public static function termChanges(): \Generator
    {
        yield 'edited term' => [
            'dataMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    1 => [
                        'term' => 'proton beams',
                    ],
                ],
            ],
            'commandMap' => [],
            'expectedResult' => 'firstFolderOutOfSync.csv',
        ];
        yield 'term saved without changes' => [
            'dataMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    1 => [
                        'term' => 'proton beam',
                    ],
                ],
            ],
            'commandMap' => [],
            'expectedResult' => 'noFolderOutOfSync.csv',
        ];
        yield 'added term' => [
            'dataMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    'NEW1' => [
                        'pid' => 2,
                        'term' => 'particle beam',
                    ],
                ],
            ],
            'commandMap' => [],
            'expectedResult' => 'firstFolderOutOfSync.csv',
        ];
        yield 'hidden term' => [
            'dataMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    1 => [
                        'hidden' => 1,
                    ],
                ],
            ],
            'commandMap' => [],
            'expectedResult' => 'firstFolderOutOfSync.csv',
        ];
        yield 'hidden term shown again' => [
            'dataMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    7 => [
                        'hidden' => 0,
                    ],
                ],
            ],
            'commandMap' => [],
            'expectedResult' => 'secondFolderOutOfSync.csv',
        ];
        yield 'deleted term' => [
            'dataMap' => [],
            'commandMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    1 => [
                        'delete' => 1,
                    ],
                ],
            ],
            'expectedResult' => 'firstFolderOutOfSync.csv',
        ];
        yield 'translated term' => [
            'dataMap' => [],
            'commandMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    3 => [
                        'localize' => 1,
                    ],
                ],
            ],
            'expectedResult' => 'firstFolderOutOfSync.csv',
        ];
        yield 'term moved to another folder' => [
            'dataMap' => [],
            'commandMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    1 => [
                        'move' => 4,
                    ],
                ],
            ],
            'expectedResult' => 'bothFoldersOutOfSync.csv',
        ];
        yield 'term copied to another folder' => [
            'dataMap' => [],
            'commandMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    1 => [
                        'copy' => 4,
                    ],
                ],
            ],
            'expectedResult' => 'secondFolderOutOfSync.csv',
        ];
        yield 'two terms copied to another folder in one run' => [
            'dataMap' => [],
            'commandMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    1 => [
                        'copy' => 4,
                    ],
                    3 => [
                        'copy' => 4,
                    ],
                ],
            ],
            'expectedResult' => 'secondFolderOutOfSync.csv',
        ];
        yield 'terms of two folders edited and deleted in one run' => [
            'dataMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    1 => [
                        'term' => 'proton beams',
                    ],
                ],
            ],
            'commandMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    5 => [
                        'delete' => 1,
                    ],
                ],
            ],
            'expectedResult' => 'bothFoldersOutOfSync.csv',
        ];
        yield 'edited glossary folder without term changes' => [
            'dataMap' => [
                'pages' => [
                    2 => [
                        'title' => 'Renamed Glossary',
                    ],
                ],
            ],
            'commandMap' => [],
            'expectedResult' => 'noFolderOutOfSync.csv',
        ];
    }

    /**
     * @param array<string, mixed> $dataMap
     * @param array<string, mixed> $commandMap
     */
    #[Test]
    #[DataProvider('termChanges')]
    public function termChangeMarksAffectedGlossariesOutOfSync(array $dataMap, array $commandMap, string $expectedResult): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, $commandMap);

        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();

        self::assertSame([], $dataHandler->errorLog);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Results/' . $expectedResult);
    }

    /**
     * Copy and localize store their records through a nested DataHandler. The folders it collects
     * are marked when the outermost DataHandler finishes, not at the end of the nested run.
     */
    #[Test]
    public function nestedDataHandlerLeavesMarkingToOutermostOne(): void
    {
        $nestedDataHandler = $this->createMock(DataHandler::class);
        $nestedDataHandler->method('isOuterMostInstance')->willReturn(false);
        $outerMostDataHandler = $this->createMock(DataHandler::class);
        $outerMostDataHandler->method('isOuterMostInstance')->willReturn(true);
        $subject = $this->get(UpdatedGlossaryEntryTermHook::class);

        $subject->processDatamap_afterDatabaseOperations(
            'update',
            'tx_deepltranslate_glossaryentry',
            1,
            ['term' => 'proton beams'],
            $nestedDataHandler
        );
        $subject->processDatamap_afterAllOperations($nestedDataHandler);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Results/noFolderOutOfSync.csv');

        $subject->processCmdmap_afterFinish($outerMostDataHandler);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Results/firstFolderOutOfSync.csv');
    }
}
