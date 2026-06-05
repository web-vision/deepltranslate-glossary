<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'DeepL Translate: Glossary',
    'description' => 'Add-on providing glossary functionality',
    'version' => '6.0.1',
    'category' => 'backend',
    'state' => 'stable',
    'author' => 'web-vision GmbH Team',
    'author_email' => 'hello@web-vision.de',
    'author_company' => 'web-vision GmbH',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.4.28-14.3.99',
            'backend' => '13.4.28-14.3.99',
            'install' => '13.4.28-14.3.99',
            'deepltranslate_core' => '6.0.3-6.0.99',
        ],
        'conflicts' => [
            'wv_deepltranslate' => '',
        ],
        'suggests' => [
            'container' => '',
            'dashboard' => '',
            'install' => '',
            'enable_translated_content' => '',
            'deepltranslate_assets' => '',
            'scheduler' => '',
        ],
    ],
];
