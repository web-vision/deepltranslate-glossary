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
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
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
        private readonly GlossarySyncPermission $glossarySyncPermission,
        private readonly FlashMessageService $flashMessageService,
        private readonly UriBuilder $uriBuilder,
        private readonly Typo3Version $typo3Version,
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
        $pageId = (int)($processingParameters['uid'] ?? 0);
        $returnUrl = $this->resolveReturnUrl((string)($processingParameters['returnUrl'] ?? ''), $pageId);

        if ($pageId === 0) {
            $this->enqueueMessage('No ID given for glossary synchronization', '', ContextualFeedbackSeverity::ERROR);
            return new RedirectResponse($returnUrl);
        }
        if (!$this->isUserAllowedToSync($pageId)) {
            $this->enqueueMessage(
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message.denied'),
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title.invalid'),
                ContextualFeedbackSeverity::ERROR
            );
            return new RedirectResponse($returnUrl);
        }

        $this->synchronise($pageId);

        return new RedirectResponse($returnUrl);
    }

    private function synchronise(int $pageId): void
    {
        try {
            $this->reportSynchronisedFolder($this->multilingualGlossaryService->syncGlossary($pageId));
        } catch (GlossaryFolderNotSyncableException $exception) {
            $this->enqueueMessage(
                $exception->getMessage(),
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title.invalid'),
                ContextualFeedbackSeverity::ERROR
            );
        } catch (DeepLException|ApiKeyNotSetException) {
            $this->enqueueMessage(
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message.failed'),
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title.invalid'),
                ContextualFeedbackSeverity::ERROR
            );
        }
    }

    private function reportSynchronisedFolder(bool $hasGlossary): void
    {
        if (!$hasGlossary) {
            $this->enqueueMessage(
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message.removed'),
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title.removed'),
                ContextualFeedbackSeverity::INFO
            );
            return;
        }

        $this->enqueueMessage(
            $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message'),
            $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title'),
            ContextualFeedbackSeverity::OK
        );
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
     * Browsers drop a tab or line break from a URL, so "/<tab>/host" would lead to another host.
     * Any character outside printable ASCII is refused for that reason.
     */
    private function resolveReturnUrl(string $returnUrl, int $pageId): string
    {
        $isLocalPath = str_starts_with($returnUrl, '/')
            && !str_starts_with($returnUrl, '//')
            && !str_contains($returnUrl, '\\')
            && preg_match('/[^\x21-\x7E]/', $returnUrl) === 0
            && parse_url($returnUrl, PHP_URL_HOST) === null;
        if ($isLocalPath) {
            return $returnUrl;
        }

        $recordsModule = match ($this->typo3Version->getMajorVersion()) {
            13 => 'web_list',
            default => 'records',
        };

        return (string)$this->uriBuilder->buildUriFromRoute($recordsModule, ['id' => $pageId]);
    }

    private function enqueueMessage(string $message, string $title, ContextualFeedbackSeverity $severity): void
    {
        $this->flashMessageService
            ->getMessageQueueByIdentifier()
            ->enqueue(new FlashMessage($message, $title, $severity, true));
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
