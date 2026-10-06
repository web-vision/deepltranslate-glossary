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
use Symfony\Contracts\Service\Attribute\Required;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollision;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Service\GlossaryLanguageCollisionMessageBuilder;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;

#[AsCommand(
    name: 'deepl:glossary:sync',
    description: 'Sync all glossaries to DeepL API'
)]
final class GlossarySyncCommand extends Command
{
    private MultilingualGlossaryService $multilingualGlossaryService;
    private GlossaryRepository $glossaryRepository;
    private GlossaryLanguageCollisionMessageBuilder $collisionMessageBuilder;
    private LanguageServiceFactory $languageServiceFactory;

    #[Required]
    public function injectMultilingualGlossaryService(MultilingualGlossaryService $multilingualGlossaryService): void
    {
        $this->multilingualGlossaryService = $multilingualGlossaryService;
    }

    #[Required]
    public function injectGlossaryRepository(GlossaryRepository $glossaryRepository): void
    {
        $this->glossaryRepository = $glossaryRepository;
    }

    #[Required]
    public function injectCollisionMessageBuilder(GlossaryLanguageCollisionMessageBuilder $collisionMessageBuilder): void
    {
        $this->collisionMessageBuilder = $collisionMessageBuilder;
    }

    #[Required]
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
        $io = new SymfonyStyle($input, $output);
        $io->title('Glossary Sync');

        $pageId = $input->getOption('pageId');
        $glossaries = $pageId !== null
            ? [['uid' => (int)$pageId]]
            : $this->glossaryRepository->findAllGlossaries();

        $errors = [];
        $collisionsByPageId = [];
        $io->progressStart(count($glossaries));
        foreach ($glossaries as $glossary) {
            // A failing folder must not keep the remaining folders from being synchronised.
            try {
                $collisionsByPageId[(int)$glossary['uid']] = $this->multilingualGlossaryService
                    ->syncGlossary((int)$glossary['uid'])
                    ->collisions;
            } catch (Exception $exception) {
                $errors[] = sprintf('Page %d: %s (%s)', $glossary['uid'], $exception->getMessage(), $exception->getCode());
            }
            $io->progressAdvance();
        }
        $io->progressFinish();
        $this->reportCollisions($io, $collisionsByPageId);

        if ($errors !== []) {
            $io->error($errors);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<int, list<GlossaryLanguageCollision>> $collisionsByPageId
     */
    private function reportCollisions(SymfonyStyle $io, array $collisionsByPageId): void
    {
        $languageService = $this->languageServiceFactory->create('default');
        foreach ($collisionsByPageId as $pageId => $collisions) {
            foreach ($collisions as $collision) {
                $io->warning(sprintf(
                    '%s: %s',
                    $this->collisionMessageBuilder->buildTitle($collision, $pageId, $languageService),
                    $this->collisionMessageBuilder->buildMessage($collision, $languageService)
                ));
            }
        }
    }
}
