<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * Every change to the terms of a synchronised folder leaves its DeepL glossary behind until the
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

        $this->importCSVDataSet(__DIR__ . '/Fixtures/syncedGlossaryFolder.csv');
        $this->setUpBackendUser(1);
    }

    /**
     * @return \Generator<string, array{dataMap: array<string, mixed>, commandMap: array<string, mixed>}>
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
        ];
        yield 'added term' => [
            'dataMap' => [
                'tx_deepltranslate_glossaryentry' => [
                    'NEW1' => [
                        'pid' => 2,
                        'term' => 'particle accelerator',
                    ],
                ],
            ],
            'commandMap' => [],
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
        ];
    }

    /**
     * @param array<string, mixed> $dataMap
     * @param array<string, mixed> $commandMap
     */
    #[Test]
    #[DataProvider('termChanges')]
    public function termChangeMarksGlossaryOutOfSync(array $dataMap, array $commandMap): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, $commandMap);

        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();

        self::assertSame([], $dataHandler->errorLog);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Results/glossaryOutOfSync.csv');
    }

    #[Test]
    public function translatedTermShowsTheOutOfSyncNoticeOnce(): void
    {
        // The notice is left out on the CLI, so the translation has to run as in the backend.
        $cli = Environment::isCli();
        $this->initializeEnvironment(false);
        try {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start([], [
                'tx_deepltranslate_glossaryentry' => [
                    3 => [
                        'localize' => 1,
                    ],
                ],
            ]);
            $dataHandler->process_cmdmap();
        } finally {
            $this->initializeEnvironment($cli);
        }

        self::assertSame([], $dataHandler->errorLog);
        self::assertCount(1, $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages());
    }

    private function initializeEnvironment(bool $cli): void
    {
        Environment::initialize(
            Environment::getContext(),
            $cli,
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );
    }
}
