<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\EventListener;

use WebVision\Deepltranslate\Core\Event\GlossaryKeyEvent;
use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;

final class LocalGlossary
{
    public function __construct(
        private readonly GlossaryRepository $glossaryRepository
    ) {
    }

    public function __invoke(GlossaryKeyEvent $event): void
    {
        $glossary = $this->glossaryRepository->getGlossaryBySourceAndTarget(
            $event->getSourceLanguage(),
            $event->getTargetLanguage(),
            $event->getCurrentPage()
        );

        if ($glossary->isReady()) {
            $event->setGlossaryId($glossary->getGlossaryId());
        }
    }
}
