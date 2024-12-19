<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Hooks;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryEntryRepository;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;

final class UpdatedGlossaryEntryTermHook
{
    private LanguageService $languageService;

    public function __construct(
        private readonly GlossaryRepository $glossaryRepository,
        private readonly GlossaryEntryRepository $glossaryEntryRepository,
        LanguageServiceFactory $languageServiceFactory
    ) {
        $this->languageService = $languageServiceFactory
            ->createFromUserPreferences($this->getBackendUser());
    }

    /**
     * @param int|string $id
     * @param array{glossary: int} $fieldArray
     *
     * @throws DBALException
     * @throws Exception
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        $id,
        array $fieldArray,
        DataHandler $dataHandler
    ): void {
        if ($status !== 'update') {
            return;
        }

        if ($table !== 'tx_deepltranslate_glossaryentry') {
            return;
        }

        $glossary = $this->glossaryEntryRepository->findEntryByUid($id);

        if (empty($glossary)) {
            return;
        }

        $this->glossaryRepository->setGlossaryNotSyncOnPage($glossary['pid']);

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
