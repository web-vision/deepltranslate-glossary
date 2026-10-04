<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Controller;

use DeepL\DeepLException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use WebVision\Deepltranslate\Core\Exception\ApiKeyNotSetException;
use WebVision\Deepltranslate\Core\Exception\InvalidArgumentException;
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

        if (!isset($processingParameters['uid'])) {
            $this->flashMessageService
                ->getMessageQueueByIdentifier()
                ->enqueue((new FlashMessage(
                    'No ID given for glossary synchronization',
                    '',
                    ContextualFeedbackSeverity::ERROR,
                    true
                )));
            return new RedirectResponse($processingParameters['returnUrl']);
        }

        try {
            $this->reportSynchronisedFolder($this->multilingualGlossaryService->syncGlossary((int)$processingParameters['uid']));
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

        return new RedirectResponse($processingParameters['returnUrl']);
    }

    private function reportSynchronisedFolder(bool $hasGlossary): void
    {
        if (!$hasGlossary) {
            $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(new FlashMessage(
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message.removed'),
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title.removed'),
                ContextualFeedbackSeverity::INFO,
                true
            ));
            return;
        }

        $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(new FlashMessage(
            $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message'),
            $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title'),
            ContextualFeedbackSeverity::OK,
            true
        ));
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
