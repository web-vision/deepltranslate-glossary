<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary',
        'label' => 'glossary_name',
        'iconfile' => 'EXT:deepltranslate_glossary/Resources/Public/Icons/deepl.svg',
        'default_sortby' => 'uid',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'hideTable' => true,
        // @todo Ensure that glossaries are only synced using live-workspace records AND also in live workspace.
        // This needs to be set to `true` to avoid automatic TCA migration together with following deprecation:
        // https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/14.0/Deprecation-106821-WorkspaceAwareInlineChildTablesAreEnforced.html
        // https://review.typo3.org/c/Packages/TYPO3.CMS/+/88739
        'versioningWS' => true,
        'enablecolumns' => [],
        'searchFields' => 'glossary_name,glossary_id,glossary_ready,glossary_lastsync',
    ],
    'inferface' => [
        'showRecordFieldList' => '',
        'maxDBListItems' => 20,
        'maxSingleDBListItems' => 100,
    ],
    'palettes' => [
        'deepl' => [
            'showitem' => 'glossary_name,--linebreak--,glossary_lastsync,glossary_ready',
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => '
            --div--;LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.tab.sync,
            glossary_id,--palette--;;deepl',
        ],
    ],
    'columns' => [
        'glossary_id' => [
            'label' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.glossary_id',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'glossary_name' => [
            'label' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.glossary_name',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'glossary_lastsync' => [
            'label' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.glossary_lastsync',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'glossary_ready' => [
            'label' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.glossary_ready',
            'config' => [
                'type' => 'check',
                'readOnly' => true,
            ],
        ],
    ],
];
