<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Hooks;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\SysLog\Action\Database as SystemLogDatabaseAction;
use TYPO3\CMS\Core\SysLog\Error as SystemLogErrorClassification;
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
 *
 * The rejection is written to the DataHandler log as a user error. The backend shows it to the
 * editor as every other error of the DataHandler run, a command line caller finds it in
 * {@see DataHandler::$errorLog}.
 */
#[Autoconfigure(public: true)]
final readonly class GlossaryEntryTermLengthValidationHook
{
    private const MAX_TERM_BYTES = 1024;

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

        // Trimmed here already: the DataHandler applies the character based TCA "max" before the
        // "trim" eval, and would otherwise cut a term that only exceeds the limit by whitespace.
        $term = trim((string)$incomingFieldArray['term']);
        $incomingFieldArray['term'] = $term;
        $termByteLength = strlen($term);
        if ($termByteLength <= self::MAX_TERM_BYTES) {
            return;
        }

        $isExistingRecord = MathUtility::canBeInterpretedAsInteger($id);
        if ($isExistingRecord) {
            // Existing record (including a translation): keep the previously stored term,
            // other submitted fields are still processed and saved.
            unset($incomingFieldArray['term']);
        } else {
            // New record: invalidating the field array makes DataHandler skip it entirely,
            // see the `continue 2` in DataHandler::process_datamap().
            $incomingFieldArray = false;
        }

        $dataHandler->log(
            $table,
            $isExistingRecord ? (int)$id : 0,
            $isExistingRecord ? SystemLogDatabaseAction::UPDATE : SystemLogDatabaseAction::INSERT,
            null,
            SystemLogErrorClassification::USER_ERROR,
            'The glossary term was not saved: it is {bytes} UTF-8 bytes long, DeepL accepts at most {limit} UTF-8 bytes'
            . ' per term. Umlauts take two bytes, CJK characters three, emoji four.',
            null,
            ['bytes' => $termByteLength, 'limit' => self::MAX_TERM_BYTES]
        );
    }
}
