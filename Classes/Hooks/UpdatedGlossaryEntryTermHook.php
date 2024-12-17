<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Hooks;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryEntryRepository;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;

final class UpdatedGlossaryEntryTermHook
{
    public function __construct(
        private readonly GlossaryRepository $glossaryRepository,
        private readonly GlossaryEntryRepository $glossaryEntryRepository
    ) {
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

        // @todo get rid of LocalizationUtility!
        $flashMessage = new FlashMessage(
            (string)LocalizationUtility::translate(
                'glossary.not-sync.message',
                'DeepltranslateCore'
            ),
            (string)LocalizationUtility::translate(
                'glossary.not-sync.title',
                'DeepltranslateCore'
            ),
            ContextualFeedbackSeverity::INFO,
            true
        );

        // @todo analyze behavior and refactor for CLI compatible mode not using flash messages
        GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier()
            ->enqueue($flashMessage);
    }
}
