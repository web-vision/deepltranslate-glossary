<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend;
use WebVision\Deepltranslate\Glossary\Hooks\UpdatedGlossaryEntryTermHook;

(static function (): void {
    // caching configuration
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['deepltranslate_glossary']
        ??= [];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['deepltranslate_glossary']['backend']
        ??= SimpleFileBackend::class;

    // hook registration
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
        = UpdatedGlossaryEntryTermHook::class;
})();
