<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use WebVision\Deepltranslate\Core\Event\DeepLGlossaryIdEvent;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;

/**
 * Listen to {@see DeepLGlossaryIdEvent} to provide suitable glossary id
 * dispatched from `EXT:deepltranslate_core`.
 *
 * @internal and not part of public API.
 */
#[Autoconfigure(public: true)]
final class LocalGlossary
{
    public function __construct(
        private readonly GlossaryRepository $glossaryRepository,
    ) {
    }

    #[AsEventListener(identifier: 'deepltranslate.localGlossary')]
    public function __invoke(DeepLGlossaryIdEvent $event): void
    {
        $glossary = $this->glossaryRepository->getGlossaryBySourceAndTarget(
            $event->sourceLanguage,
            $event->targetLanguage,
            $event->currentPage
        );

        if ($glossary->ready) {
            $event->glossaryId =  $glossary->glossaryId;
        }
    }
}
