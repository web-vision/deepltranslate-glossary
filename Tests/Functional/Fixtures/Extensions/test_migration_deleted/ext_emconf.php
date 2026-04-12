<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'DeepL Translate - Test Overrides',
    'description' => 'Change service registrations for testing purposes.',
    'category' => 'backend',
    'author' => 'web-vision GmbH Team',
    'author_company' => 'web-vision GmbH',
    'author_email' => 'hello@web-vision.de',
    'state' => 'stable',
    'version' => '1.0.0.',
    'constraints' => [
        'depends' => [
            'typo3' => '*',
            'deepltranslate_core' => '*',
            'deepltranslate_glossary' => '*',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
