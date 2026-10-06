<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Domain\Dto;

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
     */
    public function __construct(
        public bool $hasGlossary,
        public array $collisions,
    ) {
    }
}
