<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'DeepL Translate: Glossary',
    'description' => 'DeepL Translate Add-On providing glossary functionality',
    'category' => 'backend',
    'author' => 'web-vision GmbH Team',
    'author_company' => 'web-vision GmbH',
    'author_email' => 'hello@web-vision.de',
    'state' => 'stable',
    'version' => '5.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.1.0-8.4.99',
            'typo3' => '12.4.0-13.4.99',
            'backend' => '12.4.0-13.4.99',
            'setup' => '12.4.0-13.4.99',
            'deepltranslate_core' => '5.0.0-5.99.99',
        ],
        'conflicts' => [
            'wv_deepltranslate' => '*',
        ],
        'suggests' => [
            'container' => '*',
            'dashboard' => '*',
            'install' => '*',
            'enable_translated_content' => '*',
            'deepltranslate_assets' => '*',
            'scheduler' => '*',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'WebVision\\Deepltranslate\\Glossary\\' => 'Classes',
        ],
    ],
];
