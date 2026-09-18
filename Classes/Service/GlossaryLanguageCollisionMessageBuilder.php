<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Service;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollision;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollisionReason;

/**
 * Builds the user facing warning for a {@see GlossaryLanguageCollision}, used by the backend
 * synchronization and the `deepl:glossary:sync` command.
 */
final class GlossaryLanguageCollisionMessageBuilder
{
    public function buildTitle(GlossaryLanguageCollision $collision, int $pageId, LanguageService $languageService): string
    {
        return sprintf(
            $languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.collision.title'),
            $pageId,
            $collision->languageCode
        );
    }

    public function buildMessage(GlossaryLanguageCollision $collision, LanguageService $languageService): string
    {
        return sprintf(
            $languageService->sL(match ($collision->reason) {
                GlossaryLanguageCollisionReason::NoPreferredLanguage => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.collision.noPreferredLanguage',
                GlossaryLanguageCollisionReason::MultiplePreferredLanguages => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.collision.multiplePreferredLanguages',
                GlossaryLanguageCollisionReason::DefaultLanguageOwnsCode => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.collision.defaultLanguageOwnsCode',
            }),
            $collision->languageCode,
            $this->formatLanguage($collision->selectedLanguage),
            implode(', ', array_map($this->formatLanguage(...), $collision->ignoredLanguages))
        );
    }

    private function formatLanguage(SiteLanguage $language): string
    {
        return sprintf('"%s" [%d]', $language->getTitle(), $language->getLanguageId());
    }
}
