<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Service;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollision;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollisionReason;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageSelection;

/**
 * Decides which site language provides the terms of a glossary language code.
 *
 * DeepL glossaries only know base language codes like `en` or `pt`, so site languages like
 * `en_GB` and `en_US` compete for one glossary. The default language always owns its code,
 * then a site language marked as preferred in the site configuration (site language setting
 * `deeplGlossaryTerms`) wins, then the lowest language id providing terms.
 */
final class GlossaryLanguageResolver
{
    public const SITE_LANGUAGE_SETTING = 'deeplGlossaryTerms';
    public const SETTING_AUTOMATIC = 'automatic';
    public const SETTING_PREFERRED = 'preferred';

    /**
     * @param array<int, int> $termCountByLanguageId number of glossary terms per site language id
     */
    public function resolve(Site $site, array $termCountByLanguageId): GlossaryLanguageSelection
    {
        $languages = $site->getAllLanguages();
        ksort($languages);
        $languagesByCode = [];
        foreach ($languages as $language) {
            $languagesByCode[$language->getLocale()->getLanguageCode()][] = $language;
        }

        $languageIdsByCode = [];
        $collisions = [];
        foreach ($languagesByCode as $languageCode => $languagesSharingCode) {
            $languageCode = (string)$languageCode;
            $languageIdsByCode[$languageCode] = $this->selectLanguage($languagesSharingCode, $termCountByLanguageId)
                ->getLanguageId();
            $collision = $this->findCollision($languageCode, $languagesSharingCode, $termCountByLanguageId);
            if ($collision !== null) {
                $collisions[] = $collision;
            }
        }

        return new GlossaryLanguageSelection($languageIdsByCode, $collisions);
    }

    /**
     * @param non-empty-list<SiteLanguage> $languages sorted by language id
     * @param array<int, int> $termCountByLanguageId
     */
    private function selectLanguage(array $languages, array $termCountByLanguageId): SiteLanguage
    {
        if ($languages[0]->getLanguageId() === 0) {
            return $languages[0];
        }
        $preferredLanguages = $this->filterPreferred($languages);
        if ($preferredLanguages !== []) {
            return $preferredLanguages[0];
        }
        return $this->filterWithTerms($languages, $termCountByLanguageId)[0] ?? $languages[0];
    }

    /**
     * @param non-empty-list<SiteLanguage> $languages sorted by language id
     * @param array<int, int> $termCountByLanguageId
     */
    private function findCollision(
        string $languageCode,
        array $languages,
        array $termCountByLanguageId
    ): ?GlossaryLanguageCollision {
        $preferredLanguages = $this->filterPreferred($languages);
        if ($languages[0]->getLanguageId() === 0) {
            return $preferredLanguages === []
                ? null
                : $this->createCollision($languageCode, [$languages[0], ...$preferredLanguages], GlossaryLanguageCollisionReason::DefaultLanguageOwnsCode);
        }
        if ($preferredLanguages !== []) {
            return count($preferredLanguages) === 1
                ? null
                : $this->createCollision($languageCode, $preferredLanguages, GlossaryLanguageCollisionReason::MultiplePreferredLanguages);
        }
        $languagesWithTerms = $this->filterWithTerms($languages, $termCountByLanguageId);
        return count($languagesWithTerms) < 2
            ? null
            : $this->createCollision($languageCode, $languagesWithTerms, GlossaryLanguageCollisionReason::NoPreferredLanguage);
    }

    /**
     * @param non-empty-list<SiteLanguage> $languages the selected language first
     */
    private function createCollision(
        string $languageCode,
        array $languages,
        GlossaryLanguageCollisionReason $reason
    ): GlossaryLanguageCollision {
        return new GlossaryLanguageCollision(
            $languageCode,
            $languages[0],
            array_slice($languages, 1),
            $reason
        );
    }

    /**
     * @param list<SiteLanguage> $languages
     * @return list<SiteLanguage>
     */
    private function filterPreferred(array $languages): array
    {
        $preferredLanguages = [];
        foreach ($languages as $language) {
            if ($language->getLanguageId() === 0) {
                continue;
            }
            if (($language->toArray()[self::SITE_LANGUAGE_SETTING] ?? '') === self::SETTING_PREFERRED) {
                $preferredLanguages[] = $language;
            }
        }
        return $preferredLanguages;
    }

    /**
     * @param list<SiteLanguage> $languages
     * @param array<int, int> $termCountByLanguageId
     * @return list<SiteLanguage>
     */
    private function filterWithTerms(array $languages, array $termCountByLanguageId): array
    {
        $languagesWithTerms = [];
        foreach ($languages as $language) {
            if (($termCountByLanguageId[$language->getLanguageId()] ?? 0) > 0) {
                $languagesWithTerms[] = $language;
            }
        }
        return $languagesWithTerms;
    }
}
