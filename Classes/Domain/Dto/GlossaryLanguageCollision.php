<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Domain\Dto;

use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Several site languages share one glossary language code and the site configuration does not decide
 * unambiguously which one provides the glossary terms.
 */
final readonly class GlossaryLanguageCollision
{
    /**
     * @param list<SiteLanguage> $ignoredLanguages
     */
    public function __construct(
        public string $languageCode,
        public SiteLanguage $selectedLanguage,
        public array $ignoredLanguages,
        public GlossaryLanguageCollisionReason $reason,
    ) {
    }
}
