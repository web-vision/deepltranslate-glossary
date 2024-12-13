<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function (): void {
    $GLOBALS['TCA']['pages']['columns']['module']['config']['items'][] = [
        'label' => 'DeepL Glossary',
        'value' => 'glossary',
        'icon' => 'apps-pagetree-folder-contains-glossary',
    ];
    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-glossary']
        = 'apps-pagetree-folder-contains-glossary';

    $columns = [
        'glossary_information' => [
            'label' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:pages.glossary_information',
            'displayCond' => [
                'AND' => [
                    'FIELD:doktype:=:254',
                    'FIELD:module:=:glossary',
                ],
            ],
            'config' => [
                'type' => 'inline',
                'readOnly' => true,
                'foreign_table' => 'tx_deepltranslate_glossary',
                'foreign_field' => 'pid',
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('pages', $columns);

    // Register palette not necessary, as it is done in deepltranslate-core already
    ExtensionManagementUtility::addFieldsToPalette(
        'pages',
        'deepl_translate',
        'glossary_information'
    );
})();
