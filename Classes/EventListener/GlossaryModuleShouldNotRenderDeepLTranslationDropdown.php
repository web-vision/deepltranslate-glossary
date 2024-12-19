<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\EventListener;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use WebVision\Deepltranslate\Core\Event\RenderLocalizationSelectAllowed;

class GlossaryModuleShouldNotRenderDeepLTranslationDropdown
{
    public function __invoke(RenderLocalizationSelectAllowed $event): void
    {
        $request = $event->request;
        $currentPageId = $request->getQueryParams()['id'] ?? 0;
        $currentPage = BackendUtility::getRecord(
            'pages',
            (int)$currentPageId,
            'doktype,module'
        );
        if ($currentPage === null) {
            return;
        }
        if ($currentPage['doktype'] === 254 && $currentPage['module'] === 'glossary') {
            $event->renderingAllowed = false;
        }
    }
}
