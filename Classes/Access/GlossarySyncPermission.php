<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Access;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

/**
 * Decides whether a backend user may synchronise a glossary folder, for the synchronisation
 * button and the synchronisation route alike, as the route can be called without the button.
 *
 * @internal and not part of public API.
 */
final class GlossarySyncPermission
{
    public function isGranted(BackendUserAuthentication $backendUser, int $pageId): bool
    {
        return $backendUser->check('custom_options', AllowedGlossarySyncAccess::ALLOWED_GLOSSARY_SYNC)
            && $this->mayEditTerms($backendUser)
            && BackendUtility::readPageAccess($pageId, $backendUser->getPagePermsClause(Permission::CONTENT_EDIT)) !== false;
    }

    /**
     * Synchronising publishes the terms of a folder, so it requires the right to edit them.
     */
    private function mayEditTerms(BackendUserAuthentication $backendUser): bool
    {
        // @todo Use TcaSchemaFactory to access TCA configuration once TYPO3 v12 is no longer supported.
        $tableConfiguration = $GLOBALS['TCA']['tx_deepltranslate_glossaryentry']['ctrl'] ?? [];

        return !(($tableConfiguration['readOnly'] ?? false)
            || ($tableConfiguration['hideTable'] ?? false)
            || ($tableConfiguration['is_static'] ?? false)
            || (($tableConfiguration['adminOnly'] ?? false) && !$backendUser->isAdmin())
            || !$backendUser->check('tables_modify', 'tx_deepltranslate_glossaryentry')
            || !$backendUser->workspaceCanCreateNewRecord('tx_deepltranslate_glossaryentry'));
    }
}
