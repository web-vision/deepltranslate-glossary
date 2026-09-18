<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Domain\Dto;

/**
 * Glossaries of one glossary folder to send to DeepL, together with the language code collisions
 * the synchronization has to report.
 */
final readonly class GlossarySyncInformation
{
    /**
     * @param list<Glossary> $glossaries
     * @param list<GlossaryLanguageCollision> $collisions
     */
    public function __construct(
        public array $glossaries,
        public array $collisions,
    ) {
    }
}
