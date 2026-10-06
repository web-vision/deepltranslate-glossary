<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

/**
 * A copied glossary folder starts without a glossary. Its copied glossary record would carry the
 * DeepL glossary id of the original folder, so synchronising the copy would edit or remove the
 * glossary of the original folder.
 *
 * @see https://github.com/web-vision/deepltranslate-glossary/issues/106
 */
final class CopiedGlossaryFolderTest extends AbstractDeepLTestCase
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
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($GLOBALS['BE_USER']);
    }

    #[Test]
    public function copiedGlossaryFolderStartsWithoutGlossary(): void
    {
        $copyPageId = $this->copyPage(2, 1);

        self::assertSame([], $this->fetchRows('tx_deepltranslate_glossary', $copyPageId));
        self::assertSame([], $this->fetchRows('tx_deepltranslate_glossarydictionary', $copyPageId));
        // The terms are copied, the next synchronisation of the copy creates a glossary of its own.
        self::assertCount(2, $this->fetchRows('tx_deepltranslate_glossaryentry', $copyPageId));
    }

    #[Test]
    public function copyingAGlossaryFolderKeepsTheGlossaryOfTheOriginal(): void
    {
        $this->copyPage(2, 1);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Results/originalGlossaryAfterFolderCopy.csv');
    }

    #[Test]
    public function glossaryFoldersCopiedTogetherStartWithoutGlossaries(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], ['pages' => [2 => ['copy' => 1], 4 => ['copy' => 1]]]);
        $dataHandler->process_cmdmap();
        self::assertSame([], $dataHandler->errorLog);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Results/originalGlossaryAfterFolderCopy.csv');
    }

    private function copyPage(int $pageId, int $targetPageId): int
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], ['pages' => [$pageId => ['copy' => $targetPageId]]]);
        $dataHandler->process_cmdmap();
        self::assertSame([], $dataHandler->errorLog);

        $copyPageId = (int)($dataHandler->copyMappingArray_merged['pages'][$pageId] ?? 0);
        self::assertGreaterThan(0, $copyPageId);

        return $copyPageId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $table, int $pageId): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
