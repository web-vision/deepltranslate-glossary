<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Command;

use DateTime;
use DeepL\MultilingualGlossaryInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Service\Attribute\Required;
use WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV3ClientInterface;

#[AsCommand(
    name: 'deepl:glossary:list',
    description: 'List Glossary entries or entries by glossary_id'
)]
final class GlossaryListCommand extends Command
{
    private GlossaryAPIV3ClientInterface $client;

    #[Required]
    public function injectGlossaryClient(GlossaryAPIV3ClientInterface $client): void
    {
        $this->client = $client;
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

        $glossaryId = $input->getArgument('glossary_id');
        if ($glossaryId !== null) {
            $this->listSingleGlossary($io, (string)$glossaryId);

            return Command::SUCCESS;
        }

        $this->listAllGlossaries($io);

        return Command::SUCCESS;
    }

    private function listAllGlossaries(SymfonyStyle $io): void
    {
        $glossaries = $this->client->getAllGlossaries();

        $io->info('Read more here: https://developers.deepl.com/docs/customize/managing-glossaries');
        if ($glossaries === []) {
            $io->info('No Glossaries found.');
            return;
        }

        $rows = [];
        foreach ($glossaries as $glossary) {
            $rows[] = [
                $glossary->glossaryId,
                $glossary->name,
                $this->describeDictionaries($glossary),
                $glossary->creationTime->format(DateTime::ATOM),
            ];
        }

        $io->table(
            [
                'Glossary ID',
                'Name',
                'Dictionaries',
                'Creation Time',
            ],
            $rows
        );
    }

    private function listSingleGlossary(SymfonyStyle $io, string $glossaryId): void
    {
        $glossary = $this->client->getGlossary($glossaryId);
        if ($glossary->dictionaries === []) {
            $io->warning(sprintf('Glossary "%s" has no dictionaries.', $glossaryId));
            return;
        }

        $io->writeln([
            sprintf('Glossary: %s', $glossary->glossaryId),
            sprintf('Name: %s', $glossary->name),
            sprintf('Creation Time: %s', $glossary->creationTime->format(DateTime::ATOM)),
        ]);

        foreach ($glossary->dictionaries as $dictionary) {
            $io->newLine();
            $io->section(sprintf('%s => %s', $dictionary->sourceLang, $dictionary->targetLang));
            $this->renderEntries($io, $glossaryId, $dictionary->sourceLang, $dictionary->targetLang);
        }
    }

    private function renderEntries(SymfonyStyle $io, string $glossaryId, string $sourceLang, string $targetLang): void
    {
        $rows = [];
        foreach ($this->client->getGlossaryEntries($glossaryId, $sourceLang, $targetLang) as $dictionary) {
            foreach ($dictionary->entries as $source => $target) {
                $rows[] = [$source, $target];
            }
        }

        $io->table(
            [
                'source_lang: ' . $sourceLang,
                'target_lang: ' . $targetLang,
            ],
            $rows
        );
    }

    private function describeDictionaries(MultilingualGlossaryInfo $glossary): string
    {
        $pairs = [];
        foreach ($glossary->dictionaries as $dictionary) {
            $pairs[] = sprintf(
                '%s => %s (%d)',
                $dictionary->sourceLang,
                $dictionary->targetLang,
                $dictionary->entryCount
            );
        }

        return implode(', ', $pairs);
    }
}
