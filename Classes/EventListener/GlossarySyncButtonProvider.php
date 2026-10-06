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
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Access\GlossarySyncPermission;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;

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
        private GlossarySyncPermission $glossarySyncPermission,
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
            'uid,doktype,module'
        );

        if (!$id
            || $module === null
            || $normalizedParams === null
            || !empty($pageTSconfig['mod.']['SHARED.']['disableSysNoteButton'])
            || !in_array($module->getIdentifier(), $this->getAllowedModules(), true)
            || ($module->getIdentifier() === $this->getRecordsOrListModuleIdentifier() && !$this->isCreationAllowed($pageTSconfig['mod.']['web_list.'] ?? []))
            || !$this->isGlossaryFolder($page)
            || !$this->glossarySyncPermission->isGranted($this->getBackendUserAuthentication(), $id)
        ) {
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

    /**
     * The type and module the synchronisation checks with
     * {@see GlossaryRepository::isGlossaryFolder()}, so the button is offered only where the
     * route synchronises. A hidden folder still shows it, the synchronisation then reports that
     * the folder cannot be synchronised.
     *
     * @param array<string, mixed>|null $page
     */
    private function isGlossaryFolder(?array $page): bool
    {
        return $page !== null
            && (int)($page['doktype'] ?? 0) === PageRepository::DOKTYPE_SYSFOLDER
            && ($page['module'] ?? '') === 'glossary';
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
