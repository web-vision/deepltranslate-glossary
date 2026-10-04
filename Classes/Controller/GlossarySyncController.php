<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Controller;

use DeepL\DeepLException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\MathUtility;
use WebVision\Deepltranslate\Core\Exception\ApiKeyNotSetException;
use WebVision\Deepltranslate\Core\Exception\InvalidArgumentException;
use WebVision\Deepltranslate\Glossary\Access\GlossarySyncPermission;
use WebVision\Deepltranslate\Glossary\Exception\GlossaryFolderNotSyncableException;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;

/**
 * Synchronization Controller for local deepltranslate glossary
 *
 * @internal
 * This class is only for the deepltranslate glossary package and no Public API
 */
#[AsController]
final class GlossarySyncController
{
    private LanguageService $languageService;

    public function __construct(
        private readonly MultilingualGlossaryService $multilingualGlossaryService,
        private readonly FlashMessageService $flashMessageService,
        private readonly GlossarySyncPermission $glossarySyncPermission,
        private readonly UriBuilder $uriBuilder,
        LanguageServiceFactory $languageServiceFactory
    ) {
        $this->languageService = $languageServiceFactory
            ->createFromUserPreferences($this->getBackendUser());
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function update(ServerRequestInterface $request): RedirectResponse
    {
        $processingParameters = $request->getQueryParams();
        $pageId = $this->resolvePageId($processingParameters['uid'] ?? null);
        $returnUrl = $this->resolveReturnUrl($processingParameters['returnUrl'] ?? null, $pageId);

        if ($pageId === 0) {
            $this->flashMessageService
                ->getMessageQueueByIdentifier()
                ->enqueue((new FlashMessage(
                    'No ID given for glossary synchronization',
                    '',
                    ContextualFeedbackSeverity::ERROR,
                    true
                )));
            return new RedirectResponse($returnUrl);
        }

        if (!$this->isUserAllowedToSync($pageId)) {
            $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(new FlashMessage(
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message.denied'),
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title.invalid'),
                ContextualFeedbackSeverity::ERROR,
                true
            ));
            return new RedirectResponse($returnUrl);
        }

        try {
            $this->multilingualGlossaryService->syncGlossary($pageId);
            $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(new FlashMessage(
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message'),
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title'),
                ContextualFeedbackSeverity::OK,
                true
            ));
        } catch (GlossaryFolderNotSyncableException $exception) {
            $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(new FlashMessage(
                $exception->getMessage(),
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title.invalid'),
                ContextualFeedbackSeverity::ERROR,
                true
            ));
        } catch (DeepLException|ApiKeyNotSetException) {
            $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(new FlashMessage(
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message.failed'),
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title.invalid'),
                ContextualFeedbackSeverity::ERROR,
                true
            ));
        }

        return new RedirectResponse($returnUrl);
    }

    /**
     * Only a positive integer is a page id, anything else is treated as a missing id.
     */
    private function resolvePageId(mixed $uid): int
    {
        if ((!is_int($uid) && !is_string($uid)) || !MathUtility::canBeInterpretedAsInteger($uid)) {
            return 0;
        }

        return max(0, (int)$uid);
    }

    private function isUserAllowedToSync(int $pageId): bool
    {
        $backendUser = $this->getBackendUser();

        return $backendUser !== null && $this->glossarySyncPermission->isGranted($backendUser, $pageId);
    }

    /**
     * Only a path on the current host is followed, anything else leads back to the records of
     * the folder.
     *
     * Browsers drop a tab or line break from a URL and read a backslash as a slash, so
     * "/<tab>/host" or "/\host" would lead to another host. Any character outside printable
     * ASCII and any backslash is refused for that reason.
     */
    private function resolveReturnUrl(mixed $returnUrl, int $pageId): string
    {
        $isLocalPath = is_string($returnUrl)
            && str_starts_with($returnUrl, '/')
            && !str_starts_with($returnUrl, '//')
            && !str_contains($returnUrl, '\\')
            && preg_match('/[^\x21-\x7E]/', $returnUrl) === 0
            && parse_url($returnUrl, PHP_URL_HOST) === null;
        if ($isLocalPath) {
            return $returnUrl;
        }

        // "web_list" is the list module of TYPO3 v13 and an alias of the records module of v14.
        return (string)$this->uriBuilder->buildUriFromRoute('web_list', $pageId > 0 ? ['id' => $pageId] : []);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
