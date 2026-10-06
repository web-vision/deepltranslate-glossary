<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Command;

use DeepL\AuthorizationException;
use DeepL\DeepLException;
use DeepL\QuotaExceededException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\CMS\Core\Registry;
use WebVision\Deepltranslate\Core\Client\DeepLClientFactoryInterface;
use WebVision\Deepltranslate\Glossary\Command\GlossarySyncCommand;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Service\GlossaryLanguageCollisionMessageBuilder;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;
use WebVision\Deepltranslate\Glossary\Tests\Functional\Fixtures\Client\InterceptingGlossaryClient;

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

    #[Test]
    public function syncingAPageNotSetUpAsGlossaryFails(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Service/Fixtures/plainSysfolderWithEntries.csv');
        $commandTester = new CommandTester($this->get(GlossarySyncCommand::class));

        $exitCode = $commandTester->execute(['--pageId' => '5']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame([], $this->fetchGlossaryRecords(5));
    }

    #[Test]
    public function failingFolderDoesNotStopTheOtherFolders(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/failingAndSecondGlossaryFolder.csv');
        $commandTester = new CommandTester($this->get(GlossarySyncCommand::class));

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertNotSame('', $this->fetchGlossaryRecords(30)[0]['glossary_id'] ?? '');
    }

    #[Test]
    public function folderBeingSynchronisedIsReportedAsSkipped(): void
    {
        // Held by a concurrent synchronisation, for example an editor clicking the button.
        $mode = LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK;
        $lock = $this->get(LockFactory::class)->createLocker('deepltranslate_glossary_sync_2', $mode);
        $lock->acquire($mode);
        $commandTester = new CommandTester($this->get(GlossarySyncCommand::class));

        try {
            $exitCode = $commandTester->execute([]);
        } finally {
            $lock->release();
        }

        // A scheduler task must not fail because an editor synchronised the folder meanwhile.
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Page 2: skipped, the folder is being synchronised by another process.', $this->normalizeOutput($commandTester));
        self::assertSame([], $this->fetchGlossaryRecords());
    }

    #[Test]
    public function folderWithoutTermsReportsTheRemovedGlossary(): void
    {
        $commandTester = new CommandTester($this->get(GlossarySyncCommand::class));
        $commandTester->execute([]);
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossaryentry')
            ->delete('tx_deepltranslate_glossaryentry', ['pid' => 2]);

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Page 2: the folder holds no terms, so it has no DeepL glossary.', $this->normalizeOutput($commandTester));
        self::assertSame('', $this->fetchGlossaryRecords()[0]['glossary_id']);
    }

    #[Test]
    public function missingGlossaryFolderIsReported(): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('pages')
            ->update('pages', ['module' => ''], ['uid' => 2]);
        $commandTester = new CommandTester($this->get(GlossarySyncCommand::class));

        $exitCode = $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No glossary folder found.', $this->normalizeOutput($commandTester));
    }

    /**
     * @return \Generator<string, array{exception: DeepLException}>
     */
    public static function failuresOfTheAccount(): \Generator
    {
        yield 'refused API key' => [
            'exception' => new AuthorizationException('Authorization failure, check authentication key'),
        ];
        yield 'exceeded quota' => [
            'exception' => new QuotaExceededException('Quota for this billing period has been exceeded'),
        ];
    }

    #[Test]
    #[DataProvider('failuresOfTheAccount')]
    public function failureOfTheAccountAbortsTheRemainingFolders(DeepLException $exception): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/failingAndSecondGlossaryFolder.csv');
        $client = (new InterceptingGlossaryClient(new NullLogger(), $this->get(DeepLClientFactoryInterface::class)))
            ->failOn('createGlossary', $exception);
        $commandTester = new CommandTester($this->createCommandWithClient($client));

        $exitCode = $commandTester->execute([]);

        // Every remaining folder would fail the same way.
        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(1, count(array_keys($client->requests, 'createGlossary', true)));
        self::assertStringContainsString('Aborted, glossary folders left out: 2.', $this->normalizeOutput($commandTester));
        self::assertSame([], $this->fetchGlossaryRecords(30));
    }

    #[Test]
    public function exceededQuotaPointsAtTheMaximumNumberOfGlossaries(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/failingAndSecondGlossaryFolder.csv');
        $client = (new InterceptingGlossaryClient(new NullLogger(), $this->get(DeepLClientFactoryInterface::class)))
            ->failOn('createGlossary', new QuotaExceededException('Quota for this billing period has been exceeded'));
        $commandTester = new CommandTester($this->createCommandWithClient($client));

        $commandTester->execute([]);

        // A full glossary list of the account is answered the same way as an exhausted character quota.
        self::assertStringContainsString(
            'DeepL reports an exceeded quota as well when the account holds its maximum number of glossaries.',
            $this->normalizeOutput($commandTester)
        );
    }

    private function createCommandWithClient(InterceptingGlossaryClient $client): GlossarySyncCommand
    {
        $command = new GlossarySyncCommand();
        $command->injectMultilingualGlossaryService(new MultilingualGlossaryService(
            $this->get(CacheManager::class)->getCache('deepltranslate_glossary'),
            $client,
            $this->get(GlossaryRepository::class),
            $this->get(Registry::class),
            $this->get(LockFactory::class),
            new NullLogger(),
            $this->get(EventDispatcherInterface::class),
        ));
        $command->injectGlossaryRepository($this->get(GlossaryRepository::class));
        $command->injectCollisionMessageBuilder(new GlossaryLanguageCollisionMessageBuilder());
        $command->injectLanguageServiceFactory($this->get(LanguageServiceFactory::class));

        return $command;
    }

    /**
     * SymfonyStyle wraps long lines, which a test must not depend on.
     */
    private function normalizeOutput(CommandTester $commandTester): string
    {
        return (string)preg_replace('/\s+/', ' ', str_replace(' ! ', ' ', $commandTester->getDisplay()));
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
    private function fetchGlossaryRecords(int $pageId = 2): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        return $queryBuilder
            ->select('uid', 'glossary_id', 'glossary_ready')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
