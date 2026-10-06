<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Utility;

use DeepL\DeepLException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;

final class GlossaryBackendUtility
{
    public static function checkGlossaryCanCreated(string $sourceLanguage, string $targetLanguage): bool
    {
        try {
            $possibleGlossaryMatches = GeneralUtility::makeInstance(MultilingualGlossaryService::class)
                ->getPossibleLanguagePairs();
        } catch (DeepLException) {
            // A failure answers as if DeepL supported no language pair at all.
            return false;
        }
        if (!isset($possibleGlossaryMatches[$sourceLanguage])) {
            return false;
        }
        if (in_array($targetLanguage, $possibleGlossaryMatches[$sourceLanguage])) {
            return true;
        }
        return false;
    }
}
