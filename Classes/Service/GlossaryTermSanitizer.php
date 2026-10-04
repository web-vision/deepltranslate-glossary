<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Service;

/**
 * Reduces a glossary term to what DeepL accepts.
 *
 * DeepL rejects a term containing C0 or C1 control characters or a Unicode line or paragraph
 * separator, and answers the whole request with an error. Such characters, typically a tab or a
 * line break taken over from an import, are replaced by a space. DeepL rejects leading or
 * trailing Unicode whitespace as well, like a non-breaking space pasted from a word processor,
 * so it is trimmed. An empty string is returned for a term without any usable character, which
 * callers skip.
 */
final class GlossaryTermSanitizer
{
    public function sanitize(string $term): string
    {
        $term = mb_scrub($term, 'UTF-8');
        $term = (string)preg_replace('/[\x{0000}-\x{001F}\x{0080}-\x{009F}\x{2028}\x{2029}]+/u', ' ', $term);
        $term = (string)preg_replace('/ {2,}/', ' ', $term);

        return (string)preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $term);
    }
}
