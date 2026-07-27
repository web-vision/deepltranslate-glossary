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
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;

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
     */
    private function removeGlossary(string $id): bool
    {
        try {
            $this->client->deleteGlossary($id);
        } catch (GlossaryNotFoundException) {
            // Already gone at DeepL, the local synchronisation state still has to be cleared.
        }

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
            } catch (DeepLException $exception) {
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

    private function removeGlossariesWithNoSync(SymfonyStyle $io): void
    {
        $connectedGlossaries = $this->glossaryRepository->getGlossariesDeeplConnected();
        if ($connectedGlossaries === []) {
            $io->info('No glossaries with sync mismatch.');
            return;
        }
        $remoteGlossaries = $this->client->getAllGlossaries();
        if ($remoteGlossaries === []) {
            // The client returns no glossary as well when DeepL could not be asked. Detaching every
            // record then would orphan glossaries DeepL still holds.
            $io->warning(
                'DeepL lists no glossary for this API key, or the list could not be fetched (see the log).'
                . ' Nothing was detached.'
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
