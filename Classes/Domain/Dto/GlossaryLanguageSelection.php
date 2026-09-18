<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Domain\Dto;

/**
 * The site language providing the glossary terms for each glossary language code of a site.
 */
final class GlossaryLanguageSelection
{
    /**
     * @param array<string, int> $languageIdsByCode
     * @param list<GlossaryLanguageCollision> $collisions
     */
    public function __construct(
        public readonly array $languageIdsByCode,
        public readonly array $collisions,
    ) {
    }
}
