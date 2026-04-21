<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Information\Typo3Version;
use WebVision\Deepl\Base\Imaging\IconProvider\DeeplBaseSvgIconProvider;

$majorVersion = (new Typo3Version())->getMajorVersion();
return [
    'apps-pagetree-folder-contains-glossary' => ($majorVersion === 12)
        ? [
            'provider' => SvgIconProvider::class,
            'source' => 'EXT:deepltranslate_glossary/Resources/Public/Icons/deepl.svg',
        ]
        : [
            'provider' => DeeplBaseSvgIconProvider::class,
            'source' => 'EXT:deepltranslate_glossary/Resources/Public/Icons/deepl-mode-aware.svg',
        ],
];
