<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Command\GlossaryCleanupCommand;
use WebVision\Deepltranslate\Glossary\Command\GlossaryListCommand;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;

final class GlossaryMaintenanceCommandTest extends AbstractDeepLTestCase
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
        $this->get(MultilingualGlossaryService::class)->syncGlossary(2);
    }

    #[Test]
    public function listingShowsGlossaryWithItsDictionaries(): void
    {
        $commandTester = new CommandTester($this->get(GlossaryListCommand::class));

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        // A v3 glossary carries its language pairs on the dictionaries, not on itself.
        self::assertStringContainsString('en', $output);
        self::assertStringContainsString('de', $output);
        self::assertStringContainsString($this->fetchGlossaryId(), $output);
    }

    #[Test]
    public function cleanupRemovesEveryGlossaryFromTheAccount(): void
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        self::assertNotSame([], $client->getAllGlossaries());
        $commandTester = new CommandTester($this->get(GlossaryCleanupCommand::class));
        $commandTester->setInputs(['yes', 'yes']);

        $exitCode = $commandTester->execute(['--all' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame([], $client->getAllGlossaries());
        // The local record must not keep pointing at a glossary which no longer exists.
        self::assertSame('', $this->fetchGlossaryId());
        self::assertSame(0, $this->countDictionaryRecords());
    }

    private function fetchGlossaryId(): string
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        return (string)$queryBuilder
            ->select('glossary_id')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter(2, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
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
}
