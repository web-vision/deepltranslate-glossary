<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Controller;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use WebVision\Deepltranslate\Core\Exception\InvalidArgumentException;
use WebVision\Deepltranslate\Glossary\Exception\FailedToCreateGlossaryException;
use WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService;

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
        private readonly DeeplGlossaryService $deeplGlossaryService,
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
                    2,
                    true
                )));
            return new RedirectResponse($processingParameters['returnUrl']);
        }

        // Check page configuration of glossary type
        /** @var array{uid: int, doktype: string|int, module: string} $pages */
        $pages = BackendUtility::getRecord('pages', (int)$processingParameters['uid']);
        if ((int)$pages['doktype'] !== PageRepository::DOKTYPE_SYSFOLDER && $pages['module'] !== 'glossary') {
            $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(new FlashMessage(
                sprintf('Page "%d" not configured for glossary synchronization.', $pages['uid']),
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title.invalid'),
                ContextualFeedbackSeverity::ERROR,
                true
            ));
            return new RedirectResponse($processingParameters['returnUrl']);
        }

        try {
            $this->deeplGlossaryService->syncGlossaries((int)$processingParameters['uid']);
            $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(new FlashMessage(
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message'),
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title'),
                ContextualFeedbackSeverity::OK,
                true
            ));
        } catch (FailedToCreateGlossaryException) {
            $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(new FlashMessage(
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.message.invalid'),
                $this->languageService->sL('LLL:EXT:deepltranslate_glossary/Resources/Private/Language/locallang.xlf:glossary.sync.title.invalid'),
                ContextualFeedbackSeverity::ERROR,
                true
            ));
        }

        return new RedirectResponse($processingParameters['returnUrl']);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
