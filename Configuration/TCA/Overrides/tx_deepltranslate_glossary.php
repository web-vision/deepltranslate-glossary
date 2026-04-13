<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Information\Typo3Version;

(static function (): void {

    $majorVersion = (new Typo3Version())->getMajorVersion();

    // @todo typo3/cms-core:>=14.3 Remove complete if-block when TYPO3 v13 support is removed.
    if ($majorVersion === 13) {

        // Remove v14 field `searchable` options and add `searchFields` back again
        // [1] https://review.typo3.org/c/Packages/TYPO3.CMS/+/89880
        // [2] https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/14.0/Feature-106972-ConfigureSearchableFields.html#feature-106972-configure-searchable-fields
        // [3] https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/14.0/Breaking-106972-TCAControlOptionSearchFieldsRemoved.html#breaking-106972-tca-control-option-searchfields-removed
        $searchFields = null;
        foreach ($GLOBALS['TCA']['tx_deepltranslate_glossary']['columns'] as $columnName => &$columnDefinition) {
            $searchable = ($columnDefinition['config']['searchable'] ?? null);
            if ($searchable !== null) {
                unset($columnDefinition['config']['searchable']);
                if ($searchable === true) {
                    $searchFields[] = $columnName;
                }
            }
        }
        if ($searchFields !== null || $searchFields !== []) {
            $GLOBALS['TCA']['tx_deepltranslate_glossary']['ctrl']['searchFields'] = implode(
                ',',
                array_unique([
                    ...array_values($searchFields),
                    ...array_values(\TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(
                        ',',
                        (string)($GLOBALS['TCA']['tx_deepltranslate_glossary']['ctrl']['searchFields'] ?? '')
                    )),
                ]),
            );
        }

    }

})();
