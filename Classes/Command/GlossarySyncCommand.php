<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Command;

use DeepL\AuthorizationException;
use DeepL\QuotaExceededException;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Service\Attribute\Required;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use WebVision\Deepltranslate\Core\Exception\ApiKeyNotSetException;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollision;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Exception\GlossarySyncInProgressException;
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
        $pageIds = $pageId !== null
            ? [(int)$pageId]
            : array_values(array_map(static fn (array $glossary): int => (int)$glossary['uid'], $this->glossaryRepository->findAllGlossaries()));
        if ($pageIds === []) {
            $io->note('No glossary folder found. A glossary folder is a visible folder with the glossary module assigned.');
            return Command::SUCCESS;
        }

        $errors = [];
        $notes = [];
        $collisionsByPageId = [];
        $io->progressStart(count($pageIds));
        foreach ($pageIds as $index => $folderId) {
            // A failing folder must not keep the remaining folders from being synchronised, a
            // refused API key or an exceeded quota stops the command, see below.
            try {
                $result = $this->multilingualGlossaryService->syncGlossary($folderId);
                $collisionsByPageId[$folderId] = $result->collisions;
                if (!$result->hasGlossary) {
                    $notes[] = sprintf('Page %d: the folder holds no terms, so it has no DeepL glossary. A glossary published before was removed from DeepL.', $folderId);
                }
            } catch (GlossarySyncInProgressException) {
                $notes[] = sprintf('Page %d: skipped, the folder is being synchronised by another process.', $folderId);
            } catch (AuthorizationException|QuotaExceededException|ApiKeyNotSetException $exception) {
                // Every remaining folder would fail the same way, and retrying them only adds
                // requests DeepL refuses.
                $errors[] = sprintf('Page %d: %s (%s)', $folderId, $exception->getMessage(), $exception->getCode());
                if ($exception instanceof QuotaExceededException) {
                    $errors[] = 'DeepL reports an exceeded quota as well when the account holds its maximum number of'
                        . ' glossaries. Check the usage of the DeepL account and remove glossaries no longer used.';
                }
                $remaining = count($pageIds) - $index - 1;
                if ($remaining > 0) {
                    $errors[] = sprintf('Aborted, glossary folders left out: %d.', $remaining);
                }
                break;
            } catch (Exception $exception) {
                $errors[] = sprintf('Page %d: %s (%s)', $folderId, $exception->getMessage(), $exception->getCode());
            }
            $io->progressAdvance();
        }
        $io->progressFinish();
        $this->reportCollisions($io, $collisionsByPageId);
        if ($notes !== []) {
            $io->note($notes);
        }

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
