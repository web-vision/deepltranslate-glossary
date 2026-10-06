<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Command;

use DeepL\GlossaryNotFoundException;
use DeepL\MultilingualGlossaryDictionaryEntries;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\ConnectionPool;
use WebVision\Deepltranslate\Core\Client\DeepLClientFactoryInterface;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Command\GlossaryCleanupCommand;
use WebVision\Deepltranslate\Glossary\Tests\Functional\AbstractDeepLTestCase;
use WebVision\Deepltranslate\Glossary\Tests\Functional\Command\Fixtures\DeletionRefusingGlossaryClient;
use WebVision\Deepltranslate\Glossary\Upgrade\LegacyGlossaryIdStore;

/**
 * `deepl:glossary:cleanup --legacy` deletes the glossaries of the DeepL glossary API v2 the
 * upgrade wizard kept at DeepL, and nothing else of the account.
 */
final class GlossaryCleanupLegacyCommandTest extends AbstractDeepLTestCase
{
    #[Test]
    public function legacyDeletesExactlyTheGlossariesKeptByTheMigration(): void
    {
        $firstGlossaryId = $this->createRemoteGlossary('Glossary: en => de');
        $secondGlossaryId = $this->createRemoteGlossary('Glossary: en => fr');
        // Another instance sharing the API key, unknown to this database.
        $unrelatedGlossaryId = $this->createRemoteGlossary('Glossary of another instance');
        $this->get(LegacyGlossaryIdStore::class)->add([$firstGlossaryId, $secondGlossaryId]);

        $exitCode = $this->runCleanup(['--legacy' => true], ['yes']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFalse($this->existsAtDeepl($firstGlossaryId));
        self::assertFalse($this->existsAtDeepl($secondGlossaryId));
        self::assertTrue($this->existsAtDeepl($unrelatedGlossaryId));
        self::assertSame([], $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds());
        $this->get(GlossaryAPIV3ClientInterface::class)->deleteGlossary($unrelatedGlossaryId);
    }

    #[Test]
    public function legacyKeepsAGlossaryARecordPointsAt(): void
    {
        $legacyGlossaryId = $this->createRemoteGlossary('Glossary: en => de');
        $usedGlossaryId = $this->createRemoteGlossary('Glossary [2]');
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->insert('tx_deepltranslate_glossary', [
                'pid' => 2,
                'glossary_id' => $usedGlossaryId,
                'glossary_name' => 'Glossary [2]',
                'glossary_ready' => 1,
            ]);
        $this->get(LegacyGlossaryIdStore::class)->add([$legacyGlossaryId, $usedGlossaryId]);
        $commandTester = new CommandTester($this->get(GlossaryCleanupCommand::class));
        $commandTester->setInputs(['yes']);

        $exitCode = $commandTester->execute(['--legacy' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFalse($this->existsAtDeepl($legacyGlossaryId));
        self::assertTrue($this->existsAtDeepl($usedGlossaryId));
        self::assertStringContainsString('used by a glossary record', $commandTester->getDisplay());
        self::assertSame([], $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds());
        $this->get(GlossaryAPIV3ClientInterface::class)->deleteGlossary($usedGlossaryId);
    }

    #[Test]
    public function legacyContinuesPastAGlossaryDeeplRefusesToDelete(): void
    {
        $refusedGlossaryId = $this->createRemoteGlossary('Glossary: en => de');
        $deletedGlossaryId = $this->createRemoteGlossary('Glossary: en => fr');
        $this->get(LegacyGlossaryIdStore::class)->add([$refusedGlossaryId, $deletedGlossaryId]);
        $refusingClient = new DeletionRefusingGlossaryClient(new NullLogger(), $this->get(DeepLClientFactoryInterface::class));
        $refusingClient->refuseDeletionOf($refusedGlossaryId);
        $subject = $this->get(GlossaryCleanupCommand::class);
        $subject->injectGlossaryClient($refusingClient);
        $commandTester = new CommandTester($subject);
        $commandTester->setInputs(['yes']);

        $exitCode = $commandTester->execute(['--legacy' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertTrue($this->existsAtDeepl($refusedGlossaryId));
        self::assertFalse($this->existsAtDeepl($deletedGlossaryId));
        self::assertStringContainsString($refusedGlossaryId, $commandTester->getDisplay());
        // Listed for the next run.
        self::assertSame([$refusedGlossaryId], $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds());
        $this->get(GlossaryAPIV3ClientInterface::class)->deleteGlossary($refusedGlossaryId);
    }

    #[Test]
    public function legacyGlossaryAlreadyGoneAtDeeplIsNoLongerListed(): void
    {
        $glossaryId = $this->createRemoteGlossary('Glossary: en => de');
        $this->get(GlossaryAPIV3ClientInterface::class)->deleteGlossary($glossaryId);
        $this->get(LegacyGlossaryIdStore::class)->add([$glossaryId]);

        $exitCode = $this->runCleanup(['--legacy' => true], ['yes']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame([], $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds());
    }

    #[Test]
    public function legacyWithoutListedGlossariesDeletesNothing(): void
    {
        $unrelatedGlossaryId = $this->createRemoteGlossary('Glossary of another instance');
        $commandTester = new CommandTester($this->get(GlossaryCleanupCommand::class));
        $commandTester->setInputs(['yes']);

        $exitCode = $commandTester->execute(['--legacy' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No glossaries of the DeepL glossary API v2', $commandTester->getDisplay());
        self::assertTrue($this->existsAtDeepl($unrelatedGlossaryId));
        $this->get(GlossaryAPIV3ClientInterface::class)->deleteGlossary($unrelatedGlossaryId);
    }

    #[Test]
    public function legacyNotConfirmedDeletesNothing(): void
    {
        $glossaryId = $this->createRemoteGlossary('Glossary: en => de');
        $this->get(LegacyGlossaryIdStore::class)->add([$glossaryId]);

        $exitCode = $this->runCleanup(['--legacy' => true], ['no']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertTrue($this->existsAtDeepl($glossaryId));
        self::assertSame([$glossaryId], $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds());
        $this->get(GlossaryAPIV3ClientInterface::class)->deleteGlossary($glossaryId);
    }

    #[Test]
    public function glossaryDeletedByIdIsNoLongerListed(): void
    {
        $glossaryId = $this->createRemoteGlossary('Glossary: en => de');
        $keptGlossaryId = $this->createRemoteGlossary('Glossary: en => fr');
        $this->get(LegacyGlossaryIdStore::class)->add([$glossaryId, $keptGlossaryId]);

        $exitCode = $this->runCleanup(['--glossaryId' => $glossaryId], ['yes']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFalse($this->existsAtDeepl($glossaryId));
        self::assertSame([$keptGlossaryId], $this->get(LegacyGlossaryIdStore::class)->getGlossaryIds());
        $this->get(GlossaryAPIV3ClientInterface::class)->deleteGlossary($keptGlossaryId);
    }

    /**
     * @param array<string, mixed> $options
     * @param list<string> $answers
     */
    private function runCleanup(array $options, array $answers): int
    {
        $commandTester = new CommandTester($this->get(GlossaryCleanupCommand::class));
        $commandTester->setInputs($answers);

        return $commandTester->execute($options);
    }

    private function createRemoteGlossary(string $name): string
    {
        return $this->get(GlossaryAPIV3ClientInterface::class)->createGlossary($name, [
            new MultilingualGlossaryDictionaryEntries('en', 'de', ['tree' => 'Baum']),
        ])->glossaryId;
    }

    private function existsAtDeepl(string $glossaryId): bool
    {
        try {
            $this->get(GlossaryAPIV3ClientInterface::class)->getGlossary($glossaryId);
        } catch (GlossaryNotFoundException) {
            return false;
        }

        return true;
    }
}
