<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Service\Fixtures;

use WebVision\Deepltranslate\Glossary\Event\AfterGlossarySynchronizedEvent;

/**
 * Collects the dispatched events, to see what a synchronisation hands to its listeners.
 */
final class CollectingGlossarySynchronizedListener
{
    /**
     * @var list<AfterGlossarySynchronizedEvent>
     */
    public array $events = [];

    public function __invoke(AfterGlossarySynchronizedEvent $event): void
    {
        $this->events[] = $event;
    }
}
