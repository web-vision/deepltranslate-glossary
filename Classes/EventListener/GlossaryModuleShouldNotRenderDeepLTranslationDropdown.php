<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use WebVision\Deepltranslate\Core\Event\RenderLocalizationSelectAllowed;

/**
 * @todo Move into dedicated `Core13` namespace. Not required for TYPO3 v14 support.
 *
 * @internal and not part of public API.
 */
#[Autoconfigure(public: true)]
final class GlossaryModuleShouldNotRenderDeepLTranslationDropdown
{
    #[AsEventListener(identifier: 'deepltranslate-glossary/forbidDropdownRendering')]
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
