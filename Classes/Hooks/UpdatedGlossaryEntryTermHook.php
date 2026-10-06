<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Hooks;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryEntryRepository;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;

/**
 * Marks the glossaries of a folder as out of sync whenever its terms change: a term is added,
 * edited, hidden, deleted, translated, moved or copied.
 *
 * The folders are collected while the DataHandler works, and marked once at the end of the
 * outermost DataHandler run. Commands like copy or localize store their records through a
 * nested DataHandler, which leaves its folders to the outermost one.
 *
 * The DataHandler gets its hooks through `GeneralUtility::makeInstance()`, which needs a public
 * service, and the nested DataHandlers have to collect into the same shared instance.
 */
#[Autoconfigure(public: true)]
final class UpdatedGlossaryEntryTermHook
{
    private const TABLE = 'tx_deepltranslate_glossaryentry';

    /**
     * Folders whose glossaries no longer match their terms, collected until the end of the
     * outermost DataHandler run.
     *
     * @var array<int, true>
     */
    private array $outdatedPages = [];

    public function __construct(
        private readonly GlossaryRepository $glossaryRepository,
        private readonly GlossaryEntryRepository $glossaryEntryRepository,
        private readonly LanguageServiceFactory $languageServiceFactory,
        private readonly FlashMessageService $flashMessageService,
    ) {
    }

    /**
     * Collects the folder of a term which is added, edited or hidden, and the folder receiving a
     * copied or translated term.
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
        if ($table !== self::TABLE) {
            return;
        }
        if ($status === 'new') {
            if (isset($fieldArray['pid'])) {
                $this->outdatedPages[(int)$fieldArray['pid']] = true;
                return;
            }
            $this->collectPageOfEntry((int)($dataHandler->substNEWwithIDs[$id] ?? 0));
            return;
        }
        // Saving a term without changing it leaves the glossary as it is.
        if ($fieldArray === []) {
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
        if ($table !== self::TABLE || $command !== 'move') {
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
        if ($table !== self::TABLE || $command === 'copy') {
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
     * A nested DataHandler leaves its folders to the outermost one, so every folder is marked
     * and the notice is shown once per change.
     */
    private function markOutdatedPagesOutOfSync(DataHandler $dataHandler): void
    {
        if ($this->outdatedPages === [] || !$dataHandler->isOuterMostInstance()) {
            return;
        }
        $pageIds = array_keys($this->outdatedPages);
        $this->outdatedPages = [];
        foreach ($pageIds as $pageId) {
            $this->glossaryRepository->setGlossaryNotSyncOnPage($pageId);
        }

        // Flash messages do not work in CLI mode. Ensure to not run into an exception if this hook may be executed in CLI mode.
        if (Environment::isCli()) {
            return;
        }
        $languageService = $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        $flashMessage = new FlashMessage(
            $languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.not-sync.message'),
            $languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.not-sync.title'),
            ContextualFeedbackSeverity::INFO,
            true
        );
        // @todo analyze behavior and refactor for CLI compatible mode not using flash messages
        $this->flashMessageService
            ->getMessageQueueByIdentifier()
            ->enqueue($flashMessage);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
