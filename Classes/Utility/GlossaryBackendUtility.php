<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService;

final class GlossaryBackendUtility
{
    public static function checkGlossaryCanCreated(string $sourceLanguage, string $targetLanguage): bool
    {
        $possibleGlossaryMatches = GeneralUtility::makeInstance(DeeplGlossaryService::class)
            ->getPossibleGlossaryLanguageConfig();
        if (!isset($possibleGlossaryMatches[$sourceLanguage])) {
            return false;
        }
        if (in_array($targetLanguage, $possibleGlossaryMatches[$sourceLanguage])) {
            return true;
        }
        return false;
    }
}
