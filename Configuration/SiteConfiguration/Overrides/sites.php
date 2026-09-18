<?php

declare(strict_types=1);

defined('TYPO3') or die();

(static function (): void {
    $GLOBALS['SiteConfiguration']['site_language']['columns']['deeplGlossaryTerms'] = [
        'label' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:siteLanguage.deeplGlossaryTerms',
        'description' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:siteLanguage.deeplGlossaryTerms.description',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                [
                    'label' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:siteLanguage.deeplGlossaryTerms.automatic',
                    'value' => 'automatic',
                ],
                [
                    'label' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:siteLanguage.deeplGlossaryTerms.preferred',
                    'value' => 'preferred',
                ],
            ],
            'default' => 'automatic',
            'minitems' => 0,
            'maxitems' => 1,
            'size' => 1,
        ],
    ];

    $GLOBALS['SiteConfiguration']['site_language']['palettes']['deepl']['showitem']
        = ($GLOBALS['SiteConfiguration']['site_language']['palettes']['deepl']['showitem'] ?? '')
        . ', --linebreak--, deeplGlossaryTerms';
})();
