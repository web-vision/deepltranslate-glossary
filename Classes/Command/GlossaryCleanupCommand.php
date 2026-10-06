<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Command;

use DeepL\DeepLException;
use DeepL\GlossaryNotFoundException;
use DeepL\MultilingualGlossaryInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Service\Attribute\Required;
use WebVision\Deepltranslate\Core\Exception\ApiKeyNotSetException;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Upgrade\LegacyGlossaryIdStore;
use WebVision\Deepltranslate\Glossary\Upgrade\MigrateToMultilingualGlossaryWizard;

/**
 * @todo: Rename Command
 * @todo: Split command in housekeeping and remove glossary from API/remote storage
 */
#[AsCommand(
    name: 'deepl:glossary:cleanup',
    description: 'Cleanup Glossary entries in DeepL Database',
)]
final class GlossaryCleanupCommand extends Command
{
    private GlossaryAPIV3ClientInterface $client;
    private GlossaryRepository $glossaryRepository;
    private LegacyGlossaryIdStore $legacyGlossaryIdStore;

    #[Required]
    public function injectGlossaryClient(GlossaryAPIV3ClientInterface $client): void
    {
        $this->client = $client;
    }

    #[Required]
    public function injectGlossaryRepository(GlossaryRepository $glossaryRepository): void
    {
        $this->glossaryRepository = $glossaryRepository;
    }

    #[Required]
    public function injectLegacyGlossaryIdStore(LegacyGlossaryIdStore $legacyGlossaryIdStore): void
    {
        $this->legacyGlossaryIdStore = $legacyGlossaryIdStore;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'glossaryId',
                null,
                InputOption::VALUE_OPTIONAL,
                'Delete a single glossary',
                null
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Delete all glossaries according to the API key.',
            )
            ->addOption(
                'legacy',
                null,
                InputOption::VALUE_NONE,
                'Delete the glossaries of the DeepL glossary API v2 the upgrade wizard kept at DeepL.',
            )
            ->addOption(
                'notinsync',
                null,
                InputOption::VALUE_NONE,
                'Detach glossary records whose DeepL glossary no longer exists. Nothing is deleted at DeepL.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Glossary cleanup');

        $question = new ConfirmationQuestion(
            'Execute glossary cleanup',
            false
        );

        if (!$io->askQuestion($question)) {
            $io->warning('Delete not confirmed, the process is canceled.');
            return Command::SUCCESS;
        }

        // Remove single glossary by deepl-id
        $glossaryId = $input->getOption('glossaryId');
        if ($glossaryId !== null && !$this->removeGlossaries($io, [$glossaryId])) {
            return Command::FAILURE;
        }
        // Remove the glossaries of the API v2 the upgrade wizard kept
        if (!empty($input->getOption('legacy')) && !$this->removeLegacyGlossaries($io)) {
            return Command::FAILURE;
        }
        // Remove all glossaries
        if (!empty($input->getOption('all'))) {
            $glossaries = $this->client->getAllGlossaries();
            if (empty($glossaries)) {
                $io->info('No glossaries found with sync to API');
                return Command::FAILURE;
            }

            $io->warning('This will delete all glossaries from DeepL according to the actual API key.');

            $allDeletionQuestion = new ConfirmationQuestion(
                'Really delete all glossaries',
                false
            );

            if ($io->askQuestion($allDeletionQuestion) === false) {
                $io->info('Not confirmed, abort.');
                return Command::SUCCESS;
            }

            $glossaryIds = array_map(static fn (MultilingualGlossaryInfo $glossary): string => $glossary->glossaryId, $glossaries);
            if (!$this->removeGlossaries($io, $glossaryIds)) {
                return Command::FAILURE;
            }
        }
        // Remove glossaries without api sync id
        if (!empty($input->getOption('notinsync'))) {
            $this->removeGlossariesWithNoSync($io);
        }

        $io->success('Success!');

        return Command::SUCCESS;
    }

    /**
     * @throws DeepLException
     * @throws ApiKeyNotSetException
     */
    private function removeGlossary(string $id): bool
    {
        try {
            $this->client->deleteGlossary($id);
        } catch (GlossaryNotFoundException) {
            // Already gone at DeepL, the local synchronisation state still has to be cleared.
        }
        $this->legacyGlossaryIdStore->remove([$id]);

        return $this->glossaryRepository->removeGlossarySync($id);
    }

    /**
     * A glossary DeepL refuses to delete does not stop the others from being deleted.
     *
     * @param string[] $glossaryIds
     */
    private function removeGlossaries(SymfonyStyle $io, array $glossaryIds): bool
    {
        $rows = [];
        $failures = [];
        $io->progressStart(count($glossaryIds));
        foreach ($glossaryIds as $glossaryId) {
            try {
                $dbUpdated = $this->removeGlossary($glossaryId);
                $rows[] = [$glossaryId, 'yes', $dbUpdated ? 'yes' : 'no'];
            } catch (DeepLException|ApiKeyNotSetException $exception) {
                // DeepL still holds the glossary, so its folder keeps pointing at it.
                $rows[] = [$glossaryId, 'no', 'no'];
                $failures[] = sprintf('%s: %s (%d)', $glossaryId, $exception->getMessage(), $exception->getCode());
            }
            $io->progressAdvance();
        }
        $io->progressFinish();

        $io->table(
            [
                'Glossary ID',
                'Deleted at DeepL',
                'Database sync removed',
            ],
            $rows
        );
        if ($failures !== []) {
            $io->error($failures);
        }

        return $failures === [];
    }

    /**
     * Deletes exactly the glossaries {@see MigrateToMultilingualGlossaryWizard} kept at DeepL.
     * A glossary a record points at again is in use, so it is kept and no longer listed. A
     * glossary DeepL refuses to delete stays listed for the next run, without stopping the others.
     */
    private function removeLegacyGlossaries(SymfonyStyle $io): bool
    {
        $glossaryIds = $this->legacyGlossaryIdStore->getGlossaryIds();
        if ($glossaryIds === []) {
            $io->info('No glossaries of the DeepL glossary API v2 are left from the migration.');
            return true;
        }
        $referencedGlossaryIds = array_column($this->glossaryRepository->getGlossariesDeeplConnected(), 'glossary_id', 'glossary_id');

        $rows = [];
        $failures = [];
        $doneGlossaryIds = [];
        $io->progressStart(count($glossaryIds));
        foreach ($glossaryIds as $glossaryId) {
            $io->progressAdvance();
            if (isset($referencedGlossaryIds[$glossaryId])) {
                $rows[] = [$glossaryId, 'no, used by a glossary record'];
                $doneGlossaryIds[] = $glossaryId;
                continue;
            }
            try {
                $this->client->deleteGlossary($glossaryId);
                $rows[] = [$glossaryId, 'yes'];
                $doneGlossaryIds[] = $glossaryId;
            } catch (GlossaryNotFoundException) {
                $rows[] = [$glossaryId, 'already gone'];
                $doneGlossaryIds[] = $glossaryId;
            } catch (DeepLException|ApiKeyNotSetException $exception) {
                $rows[] = [$glossaryId, 'no'];
                $failures[] = sprintf('%s: %s (%d)', $glossaryId, $exception->getMessage(), $exception->getCode());
            }
        }
        $io->progressFinish();
        $this->legacyGlossaryIdStore->remove($doneGlossaryIds);

        $io->table(['Glossary ID of the API v2', 'Deleted at DeepL'], $rows);
        if ($failures !== []) {
            $io->error($failures);
        }

        return $failures === [];
    }

    private function removeGlossariesWithNoSync(SymfonyStyle $io): void
    {
        $connectedGlossaries = $this->glossaryRepository->getGlossariesDeeplConnected();
        if ($connectedGlossaries === []) {
            $io->info('No glossaries with sync mismatch.');
            return;
        }
        // A failing request to DeepL throws, so the command stops before anything is detached.
        $remoteGlossaries = $this->client->getAllGlossaries();
        if ($remoteGlossaries === []) {
            // An account without any glossary, typically another API key than the one the
            // records were synchronised with. Detaching every record then would orphan
            // glossaries the other account still holds.
            $io->warning(
                'DeepL lists no glossary for this API key. Nothing was detached, check the configured API key.'
            );
            return;
        }
        $remoteGlossaryIds = [];
        foreach ($remoteGlossaries as $remoteGlossary) {
            $remoteGlossaryIds[$remoteGlossary->glossaryId] = true;
        }
        // A record pointing at a glossary DeepL no longer knows is out of sync.
        $findNotConnected = array_filter(
            $connectedGlossaries,
            static fn (array $glossary): bool => !isset($remoteGlossaryIds[$glossary['glossary_id']])
        );
        if (count($findNotConnected) === 0) {
            $io->info('No glossaries with sync mismatch.');
            return;
        }

        $io->progressStart(count($findNotConnected));
        foreach ($findNotConnected as $notConnected) {
            $this->glossaryRepository->removeGlossarySync($notConnected['glossary_id']);
            $io->progressAdvance();
        }
        $io->progressFinish();

        $io->info(
            sprintf('Found %d glossaries with possible sync mismatch. Cleaned up.', count($findNotConnected))
        );
    }
}
