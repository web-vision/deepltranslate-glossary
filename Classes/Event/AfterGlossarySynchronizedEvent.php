<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Event;

use DeepL\MultilingualGlossaryDictionaryInfo;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollision;

/**
 * Dispatched after a glossary folder was synchronized with DeepL and the result was stored.
 *
 * Every synchronization of a folder dispatches it, from the backend and from the console command,
 * also when the scheduler runs it. A synchronization which failed or was rejected does not dispatch
 * it, the stored state of the folder is unchanged then.
 */
final readonly class AfterGlossarySynchronizedEvent
{
    /**
     * @param int $pageId the page id of the glossary folder
     * @param string $glossaryId the DeepL glossary of the folder, empty when it has none
     * @param bool $hasGlossary false when the folder holds no term, so its glossary was removed or
     *     never created
     * @param list<MultilingualGlossaryDictionaryInfo> $dictionaries the dictionaries the glossary
     *     holds at DeepL, one per language pair with its number of entries, empty when it has none
     * @param list<GlossaryLanguageCollision> $collisions site languages sharing a glossary language
     *     code the site configuration does not resolve unambiguously
     */
    public function __construct(
        public int $pageId,
        public string $glossaryId,
        public bool $hasGlossary,
        public array $dictionaries,
        public array $collisions,
    ) {
    }
}
