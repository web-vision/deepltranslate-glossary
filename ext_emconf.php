<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'DeepL Translate: Glossary',
    'description' => 'Add-on providing glossary functionality',
    'category' => 'backend',
    'author' => 'web-vision GmbH Team',
    'author_company' => 'web-vision GmbH',
    'author_email' => 'hello@web-vision.de',
    'state' => 'stable',
    'version' => '6.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.4.27-13.4.99',
            'backend' => '13.4.27-13.4.99',
            'setup' => '13.4.27-13.4.99',
            'deepltranslate_core' => '6.0.0-6.0.99',
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
];
