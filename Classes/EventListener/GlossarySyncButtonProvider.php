<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Access\AllowedGlossarySyncAccess;

/**
 * Listens to {@see ModifyButtonBarEvent} to display the `glossary sync`button
 * in `List Module` for `glossaries`.
 *
 * Allows backend users to dispatch syncing glossary from TYPO3 to DeepL.
 *
 * @internal and not part of public API.
 */
#[Autoconfigure(public: true)]
final class GlossarySyncButtonProvider
{
    public function __construct(
        private Typo3Version $typo3Version,
        private LanguageServiceFactory $languageServiceFactory,
        private IconFactory $iconFactory,
        private UriBuilder $uriBuilder,
    ) {
    }

    #[AsEventListener(identifier: 'glossary.syncbutton')]
    public function __invoke(ModifyButtonBarEvent $event): void
    {
        $buttons = $event->getButtons();
        $request = $this->getRequest($event);
        $requestParams = $request->getQueryParams();

        $id = (int)($requestParams['id'] ?? 0);
        $module = $request->getAttribute('module');
        $normalizedParams = $request->getAttribute('normalizedParams');
        $pageTSconfig = BackendUtility::getPagesTSconfig($id);

        $page = BackendUtility::getRecord(
            'pages',
            $id,
            'uid,module'
        );

        if (!$id
            || $module === null
            || $normalizedParams === null
            || !empty($pageTSconfig['mod.']['SHARED.']['disableSysNoteButton'])
            || !$this->canCreateNewRecord($id)
            || !in_array($module->getIdentifier(), $this->getAllowedModules(), true)
            || ($module->getIdentifier() === $this->getRecordsOrListModuleIdentifier() && !$this->isCreationAllowed($pageTSconfig['mod.']['web_list.'] ?? []))
            || !isset($page['module'])
            || $page['module'] !== 'glossary'
        ) {
            return;
        }

        if (!$this->getBackendUserAuthentication()->check('custom_options', AllowedGlossarySyncAccess::ALLOWED_GLOSSARY_SYNC)) {
            return;
        }

        $parameters = $this->buildParamsArrayForListView($request, (int)$id);
        $title = $this->languageServiceFactory
            ->createFromUserPreferences($GLOBALS['BE_USER'] ?? null)
            ->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.button.all');
        // Style button
        $button = $event->getButtonBar()->makeLinkButton();
        $button->setIcon($this->iconFactory->getIcon(
            'apps-pagetree-folder-contains-glossary',
            IconSize::SMALL,
        ));
        $button->setTitle($title);
        $button->setShowLabelText(true);

        $uri = $this->uriBuilder->buildUriFromRoute(
            'glossaryupdate',
            $parameters
        );
        $button->setHref((string)$uri);

        // Register Button and position it
        $buttons[ButtonBar::BUTTON_POSITION_LEFT][5][] = $button;

        $event->setButtons($buttons);
    }

    protected function getRequest(ModifyButtonBarEvent $event): ServerRequestInterface
    {
        if (method_exists($event, 'getRequest')) {
            return $event->getRequest();
        }
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
            || (!in_array('tx_deepltranslate_glossaryentry', $deniedNewTables, true)
                && ($allowedNewTables === [] || in_array('tx_deepltranslate_glossaryentry', $allowedNewTables, true)));
    }

    protected function canCreateNewRecord(int $id): bool
    {
        // @todo Use TcaSchemaFactory to access TCA configuration
        $tableConfiguration = $GLOBALS['TCA']['tx_deepltranslate_glossaryentry']['ctrl'];
        $pageRow = BackendUtility::getRecord('pages', $id);
        $backendUser = $this->getBackendUserAuthentication();

        return !($pageRow === null
            || ($tableConfiguration['readOnly'] ?? false)
            || ($tableConfiguration['hideTable'] ?? false)
            || ($tableConfiguration['is_static'] ?? false)
            || (($tableConfiguration['adminOnly'] ?? false) && !$backendUser->isAdmin())
            || !$backendUser->doesUserHaveAccess($pageRow, Permission::CONTENT_EDIT)
            || !$backendUser->check('tables_modify', 'tx_deepltranslate_glossaryentry')
            || !$backendUser->workspaceCanCreateNewRecord('tx_deepltranslate_glossaryentry'));
    }

    /**
     * @return array{uid: int, returnUrl: string|UriInterface}
     */
    private function buildParamsArrayForListView(ServerRequestInterface $request, int $id): array
    {
        return [
            'uid' => $id,
            'returnUrl' => (string)$request->getAttribute('normalizedParams')?->getRequestUri(),
        ];
    }

    /**
     * @return string[]
     */
    private function getAllowedModules(): array
    {
        return [
            $this->getPageLayoutModuleIdentifier(),
            $this->getRecordsOrListModuleIdentifier(),
        ];
    }

    private function getPageLayoutModuleIdentifier(): string
    {
        return 'web_layout';
    }

    private function getRecordsOrListModuleIdentifier(): string
    {
        return match($this->typo3Version->getMajorVersion()) {
            13 => 'web_list',
            default => 'records',
        };
    }
}
