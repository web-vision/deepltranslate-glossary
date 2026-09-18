<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Domain\Dto;

use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Several site languages share one glossary language code and the site configuration does not decide
 * unambiguously which one provides the glossary terms.
 */
final class GlossaryLanguageCollision
{
    /**
     * @param list<SiteLanguage> $ignoredLanguages
     */
    public function __construct(
        public readonly string $languageCode,
        public readonly SiteLanguage $selectedLanguage,
        public readonly array $ignoredLanguages,
        public readonly GlossaryLanguageCollisionReason $reason,
    ) {
    }
}
