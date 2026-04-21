<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Command;

use DateTime;
use DeepL\GlossaryInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Service\Attribute\Required;
use WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService;

#[AsCommand(
    name: 'deepl:glossary:list',
    description: 'List Glossary entries or entries by glossary_id'
)]
final class GlossaryListCommand extends Command
{
    private DeeplGlossaryService $deeplGlossaryService;

    #[Required]
    public function injectDeeplGlossaryService(DeeplGlossaryService $deeplGlossaryService): void
    {
        $this->deeplGlossaryService = $deeplGlossaryService;
    }

    protected function configure(): void
    {
        $this->addArgument(
            'glossary_id',
            InputArgument::OPTIONAL,
            'Which glossary you want to fetch (id)?',
            null
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Glossary List');

        $glossary_id = $input->getArgument('glossary_id');

        if ($glossary_id !== null) {
            $this->listAllGlossaryEntriesById($io, $glossary_id);
        } else {
            $this->listAllGlossaryEntries($io);
        }

        return Command::SUCCESS;
    }

    private function listAllGlossaryEntries(SymfonyStyle $io): void
    {
        $glossaries = $this->deeplGlossaryService->listGlossaries();

        $io->info('Read more here: https://www.deepl.com/docs-api/managing-glossaries/listing-glossaries/');
        if ($glossaries === []) {
            $io->info('No Glossaries found.');
            return;
        }

        $headers = [
            'Glossary ID',
            'Name',
            'Ready',
            'Source Language',
            'Target Language',
            'Creation Time',
            'Entry count',
        ];

        $rows = array_map(function (GlossaryInfo $glossary) {
            return [
                'glossaryId' => $glossary->glossaryId,
                'name' => $glossary->name,
                'ready' => $glossary->ready,
                'sourceLang' => $glossary->sourceLang,
                'targetLang' => $glossary->targetLang,
                'creationTime' => $glossary->creationTime->format(DateTime::ATOM),
                'entryCount' => $glossary->entryCount,
            ];
        }, $glossaries);

        $io->table($headers, $rows);
    }

    private function listAllGlossaryEntriesById(SymfonyStyle $io, string $id): void
    {
        $glossaryInformation = $this->deeplGlossaryService->glossaryInformation($id);
        if ($glossaryInformation === null) {
            $io->warning(sprintf('Glossary "%s" not found.', $id));
            return;
        }
        if ($glossaryInformation->entryCount === 0) {
            $io->warning(sprintf('Glossary "%s" has no entries.', $id));
            return;
        }
        $entries = $this->deeplGlossaryService->glossaryEntries($id);
        if ($entries === null) {
            $io->error(sprintf('No entries found in glossary with ID "%s", but count has "%d" entries.', $id, $glossaryInformation->entryCount));
            return;
        }

        $io->writeln([
            sprintf('Glossary entries from: %s', $glossaryInformation->glossaryId),
            sprintf('Entries count: %s', $glossaryInformation->entryCount),
            sprintf('Is ready: %s', $glossaryInformation->ready ? 'yes' : 'no'),
            sprintf('Creation Time: %s', $glossaryInformation->creationTime->format(DateTime::ATOM)),
        ]);
        $io->newLine();

        $rows = array_map(null, array_keys($entries->getEntries()), $entries->getEntries());
        $io->table(
            [
                'source_lang: ' . $glossaryInformation->sourceLang,
                'target_lang:' . $glossaryInformation->targetLang,
            ],
            $rows
        );
    }
}
