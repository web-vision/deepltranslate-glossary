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
use TYPO3\CMS\Core\SysLog\Action\Database as SystemLogDatabaseAction;
use TYPO3\CMS\Core\SysLog\Error as SystemLogErrorClassification;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Rejects a glossary term whose UTF-8 byte length exceeds DeepL's limit of 1024 bytes per
 * source/target text, see https://developers.deepl.com/api-reference/multilingual-glossaries.
 *
 * The TCA `max` option only counts characters, so a term containing umlauts, CJK or emoji can
 * pass that check while still being too long in bytes for DeepL, which then rejects the whole
 * glossary at synchronization. Cutting the value at 1024 bytes is not an option either, since
 * that risks splitting a multibyte character apart, so the value is rejected instead of
 * truncated.
 *
 * Runs as `processDatamap_preProcessFieldArray`, before {@see DataHandler} builds the field
 * array to write: a new record (a "NEW..." id) is invalidated completely, so DataHandler skips
 * creating it - unlike the later `processDatamap_postProcessFieldArray`, dropping only the
 * `term` key there still leaves DataHandler inserting the rest of the record with an empty
 * term, which both creates an unwanted row and bypasses the field's `required` setting. An
 * existing record (an id already in the database, including a translation with `l10n_parent`
 * set) only has its `term` key removed instead, so the previously stored term is kept and every
 * other submitted field is still saved.
 */
#[Autoconfigure(public: true)]
final class GlossaryEntryTermLengthValidationHook
{
    private const MAX_TERM_BYTES = 1024;

    private LanguageService $languageService;

    public function __construct(LanguageServiceFactory $languageServiceFactory)
    {
        $this->languageService = $languageServiceFactory
            ->createFromUserPreferences($this->getBackendUser());
    }

    /**
     * @param array<string, mixed>|false $incomingFieldArray
     */
    public function processDatamap_preProcessFieldArray(
        array|false &$incomingFieldArray,
        string $table,
        int|string $id,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'tx_deepltranslate_glossaryentry' || !is_array($incomingFieldArray) || !isset($incomingFieldArray['term'])) {
            return;
        }

        $termByteLength = strlen(trim((string)$incomingFieldArray['term']));
        if ($termByteLength <= self::MAX_TERM_BYTES) {
            return;
        }

        if (MathUtility::canBeInterpretedAsInteger($id)) {
            // Existing record (including a translation): keep the previously stored term,
            // other submitted fields are still processed and saved.
            unset($incomingFieldArray['term']);
        } else {
            // New record: invalidating the field array makes DataHandler skip it entirely,
            // see the `continue 2` in DataHandler::process_datamap().
            $incomingFieldArray = false;
        }

        $this->reportRejectedTerm($table, $id, $termByteLength, $dataHandler);
    }

    private function reportRejectedTerm(string $table, int|string $id, int $termByteLength, DataHandler $dataHandler): void
    {
        $message = $this->buildMessage($termByteLength);
        $dataHandler->log(
            $table,
            MathUtility::canBeInterpretedAsInteger($id) ? (int)$id : 0,
            SystemLogDatabaseAction::CHECK,
            null,
            SystemLogErrorClassification::USER_ERROR,
            $message
        );
        $this->enqueueFlashMessage($message);
    }

    private function buildMessage(int $termByteLength): string
    {
        return sprintf(
            $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossaryentry.term.tooLong.message'),
            $termByteLength,
            self::MAX_TERM_BYTES
        );
    }

    private function enqueueFlashMessage(string $message): void
    {
        if (Environment::isCli()) {
            return;
        }

        $flashMessage = new FlashMessage(
            $message,
            $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossaryentry.term.tooLong.title'),
            ContextualFeedbackSeverity::ERROR,
            true
        );
        GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier()
            ->enqueue($flashMessage);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
