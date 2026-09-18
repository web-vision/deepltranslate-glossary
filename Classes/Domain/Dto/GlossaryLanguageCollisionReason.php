<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Domain\Dto;

/**
 * Why several site languages sharing one glossary language code needed a decision nobody made explicitly.
 */
enum GlossaryLanguageCollisionReason: string
{
    /**
     * Several site languages provide terms and none is marked as preferred.
     */
    case NoPreferredLanguage = 'noPreferredLanguage';

    /**
     * Several site languages are marked as preferred.
     */
    case MultiplePreferredLanguages = 'multiplePreferredLanguages';

    /**
     * A site language sharing the code of the default language is marked as preferred.
     */
    case DefaultLanguageOwnsCode = 'defaultLanguageOwnsCode';
}
