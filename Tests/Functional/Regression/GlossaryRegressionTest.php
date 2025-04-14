<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Regression;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class GlossaryRegressionTest extends AbstractDeepLTestCase
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

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'deepltranslate_core' => [
                'apiKey' => 'mock_server',
            ],
        ],
    ];

    protected array $testExtensionsToLoad = [
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

        $this->importCSVDataSet(__DIR__ . '/Fixtures/glossary.csv');

        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);
        GeneralUtility::makeInstance(DeeplGlossaryService::class)
            ->syncGlossaries(2);
    }

    #[Test]
    public function glossaryIsRespectedOnLocalization(): void
    {
        $commandMap = [
            'pages' => [
                4 => [
                    'deepltranslate' => 1,
                ],
            ],
        ];
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $commandMap);
        $dataHandler->process_cmdmap();

        self::assertEmpty($dataHandler->errorLog);
        self::assertCSVDataSet(__DIR__ . '/Fixtures/Results/translateWithGlossary.csv');
    }
}
