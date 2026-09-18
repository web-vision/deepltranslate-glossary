<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Command;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollision;
use WebVision\Deepltranslate\Glossary\Service\GlossaryLanguageCollisionMessageBuilder;

#[AsCommand(
    name: 'deepl:glossary:sync',
    description: 'Sync all glossaries to DeepL API'
)]
final class GlossarySyncCommand extends Command
{
    use GlossaryCommandTrait;

    private SymfonyStyle $io;

    private GlossaryLanguageCollisionMessageBuilder $collisionMessageBuilder;

    private LanguageServiceFactory $languageServiceFactory;

    public function injectCollisionMessageBuilder(GlossaryLanguageCollisionMessageBuilder $collisionMessageBuilder): void
    {
        $this->collisionMessageBuilder = $collisionMessageBuilder;
    }

    public function injectLanguageServiceFactory(LanguageServiceFactory $languageServiceFactory): void
    {
        $this->languageServiceFactory = $languageServiceFactory;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'pageId',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Page to sync. If not set, all glossaries are synced',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Glossary Sync');

        try {
            $pageId = $input->getOption('pageId');
            if ($pageId !== null) {
                $glossaries[] = ['uid' => (int)$pageId];
            } else {
                $glossaries = $this->glossaryRepository->findAllGlossaries();
            }

            $this->io->progressStart(count($glossaries));
            $collisionsByPageId = [];
            foreach ($glossaries as $glossary) {
                $collisionsByPageId[(int)$glossary['uid']] = $this->deeplGlossaryService->syncGlossaries($glossary['uid']);
                $this->io->progressAdvance();
            }
            $this->io->progressFinish();
            $this->reportCollisions($collisionsByPageId);
        } catch (Exception $exception) {
            $this->io->error(sprintf('%s (%s)', $exception->getMessage(), $exception->getCode()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<int, list<GlossaryLanguageCollision>> $collisionsByPageId
     */
    private function reportCollisions(array $collisionsByPageId): void
    {
        $languageService = $this->languageServiceFactory->create('default');
        foreach ($collisionsByPageId as $pageId => $collisions) {
            foreach ($collisions as $collision) {
                $this->io->warning(sprintf(
                    '%s: %s',
                    $this->collisionMessageBuilder->buildTitle($collision, $pageId, $languageService),
                    $this->collisionMessageBuilder->buildMessage($collision, $languageService)
                ));
            }
        }
    }
}
