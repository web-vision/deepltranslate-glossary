<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Event;

/**
 * Event is dispatched when the sync to DeepL was finished.
 *
 * It carries the page ID of the synched glossary.
 */
final class GlossarySyncDone
{
    public function __construct(public readonly int $pageId)
    {
    }
}
