<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Domain\Dto;

/**
 * The dictionaries of one glossary folder to send to DeepL, together with the language code
 * collisions the synchronization has to report.
 *
 * @internal and not part of public API.
 */
final readonly class GlossarySyncInformation
{
    /**
     * @param list<array{sourceLanguage: string, targetLanguage: string, entries: array<string, string>}> $dictionaries
     * @param list<GlossaryLanguageCollision> $collisions
     */
    public function __construct(
        public array $dictionaries,
        public array $collisions,
    ) {
    }
}
