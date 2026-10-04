<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Event;

/**
 * Allows changing the name a glossary folder is published under at DeepL.
 *
 * Dispatched when the glossary record of a folder is created by its first synchronization or
 * migrated by the upgrade wizard. The name of an existing glossary is kept. An empty name falls
 * back to the default name, as DeepL refuses a glossary without one.
 */
final class ModifyGlossaryNameEvent
{
    public function __construct(
        public readonly int $pageId,
        public readonly string $folderTitle,
        public string $glossaryName,
    ) {
    }
}
