<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use WebVision\Deepltranslate\Glossary\Command\GlossarySyncCommand;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class GlossarySyncCommandTest extends AbstractDeepLTestCase
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

        $this->importCSVDataSet(__DIR__ . '/../Service/Fixtures/glossaryFolder.csv');
        $this->setUpBackendUser(1);
    }

    #[Test]
    public function syncingASinglePageCreatesTheGlossary(): void
    {
        $commandTester = new CommandTester($this->get(GlossarySyncCommand::class));

        $exitCode = $commandTester->execute(['--pageId' => '2']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $glossaries = $this->fetchGlossaryRecords();
        self::assertCount(1, $glossaries);
        self::assertNotSame('', $glossaries[0]['glossary_id']);
        // Proves the command went through the API v3 path: only that one stores dictionaries.
        self::assertSame(1, $this->countDictionaryRecords());
    }

    #[Test]
    public function syncingAllGlossaryFoldersCreatesTheGlossary(): void
    {
        $commandTester = new CommandTester($this->get(GlossarySyncCommand::class));

        $exitCode = $commandTester->execute([]);

        // The folder is picked up through its glossary module assignment.
        self::assertSame(Command::SUCCESS, $exitCode);
        $glossaries = $this->fetchGlossaryRecords();
        self::assertCount(1, $glossaries);
        self::assertNotSame('', $glossaries[0]['glossary_id']);
        self::assertSame(1, $this->countDictionaryRecords());
    }

    private function countDictionaryRecords(): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossarydictionary');

        return (int)$queryBuilder
            ->count('uid')
            ->from('tx_deepltranslate_glossarydictionary')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchGlossaryRecords(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        return $queryBuilder
            ->select('uid', 'glossary_id', 'glossary_ready')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter(2, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
