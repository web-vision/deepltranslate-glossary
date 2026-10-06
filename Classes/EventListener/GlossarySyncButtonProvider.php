<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Access\GlossarySyncPermission;
use WebVision\Deepltranslate\Glossary\Controller\GlossarySyncController;

final class GlossarySyncButtonProvider
{
    private const TABLE_NAME = 'tx_deepltranslate_glossaryentry';

    private const ALLOWED_MODULES = [
        'web_layout',
        'web_list',
    ];

    public function __construct(
        private readonly GlossarySyncPermission $glossarySyncPermission,
    ) {
    }

    public function __invoke(ModifyButtonBarEvent $event): void
    {
        $buttons = $event->getButtons();
        $request = $this->getRequest();

        $requestParams = $request->getQueryParams();

        $id = (int)($requestParams['id'] ?? 0);
        $module = $request->getAttribute('module');
        $normalizedParams = $request->getAttribute('normalizedParams');
        $pageTSconfig = BackendUtility::getPagesTSconfig($id);

        $page = BackendUtility::getRecord(
            'pages',
            $id,
            'uid,doktype,module'
        );

        if (!$id
            || $module === null
            || $normalizedParams === null
            || !empty($pageTSconfig['mod.']['SHARED.']['disableSysNoteButton'])
            || !in_array($module->getIdentifier(), self::ALLOWED_MODULES, true)
            || ($module->getIdentifier() === 'web_list' && !$this->isCreationAllowed($pageTSconfig['mod.']['web_list.'] ?? []))
            || !$this->isGlossaryFolder($page)
            || !$this->glossarySyncPermission->isGranted($this->getBackendUserAuthentication(), $id)
        ) {
            return;
        }

        $parameters = $this->buildParamsArrayForListView((int)$id);
        $title = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER'] ?? null)
            ->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.button.all');
        // Style button
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $button = $event->getButtonBar()->makeLinkButton();
        $button->setIcon($iconFactory->getIcon(
            'apps-pagetree-folder-contains-glossary',
            Icon::SIZE_SMALL
        ));
        $button->setTitle($title);
        $button->setShowLabelText(true);

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $uri = $uriBuilder->buildUriFromRoute(
            'glossaryupdate',
            $parameters
        );
        $button->setHref((string)$uri);

        // Register Button and position it
        $buttons[ButtonBar::BUTTON_POSITION_LEFT][5][] = $button;

        $event->setButtons($buttons);
    }

    /**
     * Same condition as the guard of {@see GlossarySyncController::update()},
     * the button is offered only where the route synchronises.
     *
     * @param array<string, mixed>|null $page
     */
    private function isGlossaryFolder(?array $page): bool
    {
        return $page !== null
            && (int)($page['doktype'] ?? 0) === PageRepository::DOKTYPE_SYSFOLDER
            && ($page['module'] ?? '') === 'glossary';
    }

    protected function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }

    protected function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @param array<int|string, mixed> $modTSconfig
     */
    protected function isCreationAllowed(array $modTSconfig): bool
    {
        $allowedNewTables = GeneralUtility::trimExplode(',', $modTSconfig['allowedNewTables'] ?? '', true);
        $deniedNewTables = GeneralUtility::trimExplode(',', $modTSconfig['deniedNewTables'] ?? '', true);

        return ($allowedNewTables === [] && $deniedNewTables === [])
            || (!in_array(self::TABLE_NAME, $deniedNewTables)
                && ($allowedNewTables === [] || in_array(self::TABLE_NAME, $allowedNewTables)));
    }

    /**
     * @return array{uid: int, returnUrl: string|UriInterface}
     */
    private function buildParamsArrayForListView(int $id): array
    {
        return [
            'uid' => $id,
            'returnUrl' => (string)$this->getRequest()->getAttribute('normalizedParams')?->getRequestUri(),
        ];
    }
}
