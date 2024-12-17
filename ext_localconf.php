<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend;

(static function (): void {
    // caching configuration
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['deepltranslate_glossary']
        ??= [];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['deepltranslate_glossary']['backend']
        ??= SimpleFileBackend::class;
})();
