<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\EventListener;

use WebVision\Deepltranslate\Core\Event\DeepLGlossaryIdEvent;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;

final class LocalGlossary
{
    public function __construct(
        private readonly GlossaryRepository $glossaryRepository
    ) {
    }

    public function __invoke(DeepLGlossaryIdEvent $event): void
    {
        $glossary = $this->glossaryRepository->getGlossaryBySourceAndTarget(
            $event->sourceLanguage,
            $event->targetLanguage,
            $event->currentPage
        );

        if ($glossary->isReady()) {
            $event->glossaryId =  $glossary->getGlossaryId();
        }
    }
}
