<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Hooks;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryEntryRepository;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;

#[Autoconfigure(public: true)]
final class UpdatedGlossaryEntryTermHook
{
    private LanguageService $languageService;

    /**
     * Folders whose glossary no longer matches their terms, collected during one DataHandler run.
     *
     * @var array<int, true>
     */
    private array $outdatedPages = [];

    public function __construct(
        private readonly GlossaryRepository $glossaryRepository,
        private readonly GlossaryEntryRepository $glossaryEntryRepository,
        LanguageServiceFactory $languageServiceFactory,
    ) {
        $this->languageService = $languageServiceFactory
            ->createFromUserPreferences($this->getBackendUser());
    }

    /**
     * Collects the folder of a term which is added or edited.
     *
     * @param int|string $id
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        $id,
        array $fieldArray,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'tx_deepltranslate_glossaryentry') {
            return;
        }
        if ($status === 'new' && isset($fieldArray['pid'])) {
            $this->outdatedPages[(int)$fieldArray['pid']] = true;
            return;
        }
        $this->collectPageOfEntry((int)$id);
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $this->markOutdatedPagesOutOfSync($dataHandler);
    }

    /**
     * Collects the folder a term is moved away from, as only the target is known afterwards.
     *
     * @param int|string $id
     * @param mixed $value
     */
    public function processCmdmap_preProcess(
        string $command,
        string $table,
        $id,
        $value,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'tx_deepltranslate_glossaryentry' || $command !== 'move') {
            return;
        }
        $this->collectPageOfEntry((int)$id);
    }

    /**
     * Collects the folder of a term which is deleted, translated or moved.
     *
     * A copied term leaves its source folder untouched. The folder receiving the copy is collected
     * when the copy is stored, see {@see self::processDatamap_afterDatabaseOperations()}.
     *
     * @param int|string $id
     * @param mixed $value
     */
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        $id,
        $value,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'tx_deepltranslate_glossaryentry' || $command === 'copy') {
            return;
        }
        $this->collectPageOfEntry((int)$id);
    }

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        $this->markOutdatedPagesOutOfSync($dataHandler);
    }

    private function collectPageOfEntry(int $entryUid): void
    {
        $pageId = $this->glossaryEntryRepository->findPageOfEntry($entryUid);
        if ($pageId !== null) {
            $this->outdatedPages[$pageId] = true;
        }
    }

    /**
     * A command like localize or copy stores its records through a nested DataHandler. Its folders
     * are left to the outermost one, so the notice is shown once per change.
     */
    private function markOutdatedPagesOutOfSync(DataHandler $dataHandler): void
    {
        if ($this->outdatedPages === [] || !$dataHandler->isOuterMostInstance()) {
            return;
        }
        foreach (array_keys($this->outdatedPages) as $pageId) {
            $this->glossaryRepository->setGlossaryNotSyncOnPage($pageId);
        }
        $this->outdatedPages = [];

        // Flash messages do not work in CLI mode. Ensure to not run into an exception if this hook may be executed in CLI mode.
        if (Environment::isCli()) {
            return;
        }
        $flashMessage = new FlashMessage(
            $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.not-sync.message'),
            $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.not-sync.title'),
            ContextualFeedbackSeverity::INFO,
            true
        );
        // @todo analyze behavior and refactor for CLI compatible mode not using flash messages
        GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier()
            ->enqueue($flashMessage);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
