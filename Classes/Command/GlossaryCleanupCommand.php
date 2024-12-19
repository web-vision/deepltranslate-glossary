<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Command;

use DeepL\GlossaryInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * ToDo: Rename Command
 * ToDo: Split command in housekeeping and remove glossary from API/remote storage
 */
#[AsCommand(
    name: 'deepl:glossary:cleanup',
    description: 'Cleanup Glossary entries in DeepL Database'
)]
final class GlossaryCleanupCommand extends Command
{
    use GlossaryCommandTrait;

    private SymfonyStyle $io;

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
                'Delete all Glossaries without synchronization information',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Glossary cleanup');

        $question = new ConfirmationQuestion(
            'Execute glossary cleanup',
            false
        );

        if (!$this->io->askQuestion($question)) {
            $this->io->warning('Delete not confirmed, the process is canceled.');
            return Command::SUCCESS;
        }

        // Remove single glossary by deepl-id
        $glossaryId = $input->getOption('glossaryId');
        if ($glossaryId !== null) {
            $this->removeGlossaries($glossaryId);
        }
        // Remove all glossaries
        if (!empty($input->getOption('all'))) {
            $glossaries = $this->deeplGlossaryService->listGlossaries();
            if (empty($glossaries)) {
                $this->io->info('No glossaries found with sync to API');
                return Command::FAILURE;
            }

            $this->io->warning('This will delete all glossaries from DeepL according to the actual API key.');

            $allDeletionQuestion = new ConfirmationQuestion(
                'Really delete all glossaries',
                false
            );

            if ($this->io->askQuestion($allDeletionQuestion) === false) {
                $this->io->info('Not confirmed, abort.');
                return Command::SUCCESS;
            }

            $this->removeGlossaries($glossaries);
        }
        // Remove glossaries without api sync id
        if (!empty($input->getOption('notinsync'))) {
            $this->removeGlossariesWithNoSync();
        }

        $this->io->success('Success!');

        return Command::SUCCESS;
    }

    private function removeGlossary(string $id): bool
    {
        $this->deeplGlossaryService->deleteGlossary($id);
        return $this->glossaryRepository->removeGlossarySync($id);
    }

    /**
     * @param GlossaryInfo[] $glossaries
     */
    private function removeGlossaries(array $glossaries): void
    {
        $rows = [];
        $this->io->progressStart(count($glossaries));

        foreach ($glossaries as $glossary) {
            $dbUpdated = $this->removeGlossary($glossary->glossaryId);
            $rows[] = [$glossary->glossaryId, $dbUpdated ? 'yes' : 'no'];
            $this->io->progressAdvance();
        }

        $this->io->progressFinish();

        $this->io->table(
            [
                'Glossary ID',
                'Database sync removed',
            ],
            $rows
        );
    }

    private function removeGlossariesWithNoSync(): void
    {
        $findNotConnected = $this->glossaryRepository->getGlossariesDeeplConnected();

        if (count($findNotConnected) === 0) {
            $this->io->info('No glossaries with sync mismatch.');
        }

        $this->io->progressStart(count($findNotConnected));
        foreach ($findNotConnected as $notConnected) {
            $this->glossaryRepository->removeGlossarySync($notConnected['glossary_id']);
            $this->io->progressAdvance();
        }
        $this->io->progressFinish();

        $this->io->info(
            sprintf('Found %d glossaries with possible sync mismatch. Cleaned up.', count($findNotConnected))
        );
    }
}
