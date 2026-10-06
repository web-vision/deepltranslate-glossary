<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Domain\Dto;

use DeepL\MultilingualGlossaryDictionaryInfo;

/**
 * The outcome of synchronising one glossary folder.
 *
 * @internal and not part of public API.
 */
final readonly class GlossarySyncResult
{
    /**
     * @param bool $hasGlossary false when the folder holds no term any more, so its glossary was removed
     * @param list<GlossaryLanguageCollision> $collisions site languages sharing a glossary language code
     *     the site configuration does not resolve unambiguously, to be reported to the user
     * @param string $glossaryId the DeepL glossary of the folder, empty when it has none
     * @param list<MultilingualGlossaryDictionaryInfo> $dictionaries the dictionaries the glossary holds
     *     at DeepL after the synchronisation, empty when it has none
     */
    public function __construct(
        public bool $hasGlossary,
        public array $collisions,
        public string $glossaryId,
        public array $dictionaries,
    ) {
    }
}
