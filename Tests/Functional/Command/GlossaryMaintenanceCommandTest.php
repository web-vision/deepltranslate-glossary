<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Command;

use DeepL\MultilingualGlossaryDictionaryEntries;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use WebVision\Deepltranslate\Core\Client\DeepLClientFactoryInterface;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Command\GlossaryCleanupCommand;
use WebVision\Deepltranslate\Glossary\Command\GlossaryListCommand;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;
use WebVision\Deepltranslate\Glossary\Tests\Functional\Command\Fixtures\DeletionRefusingGlossaryClient;

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

    #[Test]
    public function cleanupOfAllGlossariesContinuesPastAGlossaryDeeplRefusesToDelete(): void
    {
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        $refusedGlossaryId = $this->fetchGlossaryId();
        $client->createGlossary('Glossary of another instance', [
            new MultilingualGlossaryDictionaryEntries('en', 'de', ['tree' => 'Baum']),
        ]);
        $refusingClient = new DeletionRefusingGlossaryClient(new NullLogger(), $this->get(DeepLClientFactoryInterface::class));
        $refusingClient->refuseDeletionOf($refusedGlossaryId);
        $subject = $this->get(GlossaryCleanupCommand::class);
        $subject->injectGlossaryClient($refusingClient);
        $commandTester = new CommandTester($subject);
        $commandTester->setInputs(['yes', 'yes']);

        $exitCode = $commandTester->execute(['--all' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        $remainingGlossaries = $client->getAllGlossaries();
        self::assertCount(1, $remainingGlossaries);
        self::assertSame($refusedGlossaryId, $remainingGlossaries[0]->glossaryId);
        // The glossary is still in use at DeepL, so its folder has to keep pointing at it.
        self::assertSame($refusedGlossaryId, $this->fetchGlossaryId());
        self::assertSame(1, $this->countDictionaryRecords());
        self::assertStringContainsString($refusedGlossaryId, $commandTester->getDisplay());
        // The mock server keeps its glossaries across the tests of this class.
        $client->deleteGlossary($refusedGlossaryId);
    }

    #[Test]
    public function cleanupOfUnsyncedGlossariesDetachesOnlyRecordsUnknownToDeepl(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/staleGlossary.csv');
        $syncedGlossaryId = $this->fetchGlossaryId();
        $commandTester = new CommandTester($this->get(GlossaryCleanupCommand::class));
        $commandTester->setInputs(['yes']);

        $exitCode = $commandTester->execute(['--notinsync' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame('', $this->fetchGlossaryIdByUid(100));
        self::assertSame(0, $this->countDictionaryRecordsOfGlossary(100));
        // A glossary DeepL still knows is in sync and has to stay attached to its folder.
        self::assertSame($syncedGlossaryId, $this->fetchGlossaryId());
        self::assertCount(1, $this->get(GlossaryAPIV3ClientInterface::class)->getAllGlossaries());
    }

    /**
     * An account listing no glossary at all, for example because another API key is configured,
     * must not make every record look stale.
     */
    #[Test]
    public function cleanupOfUnsyncedGlossariesDetachesNothingWhenDeeplListsNoGlossary(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/staleGlossary.csv');
        $syncedGlossaryId = $this->fetchGlossaryId();
        $client = $this->get(GlossaryAPIV3ClientInterface::class);
        // The mock server keeps the glossaries of earlier tests, remove every one of them.
        foreach ($client->getAllGlossaries() as $remoteGlossary) {
            $client->deleteGlossary($remoteGlossary->glossaryId);
        }
        self::assertSame([], $client->getAllGlossaries());
        $commandTester = new CommandTester($this->get(GlossaryCleanupCommand::class));
        $commandTester->setInputs(['yes']);

        $exitCode = $commandTester->execute(['--notinsync' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Nothing was detached', $commandTester->getDisplay());
        self::assertSame('3f2b0000-0000-0000-0000-00000000dead', $this->fetchGlossaryIdByUid(100));
        self::assertSame(1, $this->countDictionaryRecordsOfGlossary(100));
        self::assertSame($syncedGlossaryId, $this->fetchGlossaryId());
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

    private function fetchGlossaryIdByUid(int $uid): string
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        return (string)$queryBuilder
            ->select('glossary_id')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
    }

    private function countDictionaryRecordsOfGlossary(int $glossaryUid): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossarydictionary');

        return (int)$queryBuilder
            ->count('uid')
            ->from('tx_deepltranslate_glossarydictionary')
            ->where(
                $queryBuilder->expr()->eq(
                    'glossary',
                    $queryBuilder->createNamedParameter($glossaryUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();
    }
}
