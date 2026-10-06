<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Event;

/**
 * Allows changing the name a glossary folder is published under at DeepL.
 *
 * Dispatched when the glossary record of a folder is created by its first synchronization or
 * migrated by the upgrade wizard. The name of an existing glossary is kept. An empty name falls
 * back to the default name, as DeepL refuses a glossary without one, and a name longer than 255
 * characters is cut.
 *
 * Listeners run in the backend, on the command line and in the install tool, so they cannot rely
 * on a request, a backend user or a frontend context.
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
