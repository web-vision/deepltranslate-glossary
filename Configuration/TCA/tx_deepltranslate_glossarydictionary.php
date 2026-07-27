<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossarydictionary',
        'label' => 'source_lang',
        'label_alt' => 'target_lang',
        'label_alt_force' => true,
        'iconfile' => 'EXT:deepltranslate_glossary/Resources/Public/Icons/deepl-mode-aware.svg',
        'default_sortby' => 'source_lang,target_lang',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'hideTable' => true,
        // See the note on the parent table: synchronisation is meant to happen in live workspace
        // only, the flag avoids the automatic TCA migration for inline child tables.
        'versioningWS' => true,
        'enablecolumns' => [],
    ],
    'columns' => [
        'source_lang' => [
            'label' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossarydictionary.source_lang',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
                'searchable' => true,
            ],
        ],
        'target_lang' => [
            'label' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossarydictionary.target_lang',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
                'searchable' => true,
            ],
        ],
        'entry_count' => [
            'label' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossarydictionary.entry_count',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'in_sync' => [
            'label' => 'LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossarydictionary.in_sync',
            'config' => [
                'type' => 'check',
                'readOnly' => true,
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'source_lang,target_lang,entry_count,in_sync',
        ],
    ],
];
