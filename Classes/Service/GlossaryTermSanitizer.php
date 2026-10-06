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
 *
 * DeepL also rejects a term longer than 1024 UTF-8 bytes. Such a term is not cut, as that could
 * split a multibyte character, callers skip the pair instead, see {@see self::exceedsByteLimit()}.
 */
final class GlossaryTermSanitizer
{
    /**
     * The limit of DeepL for the source and the target text of a glossary entry, see
     * https://developers.deepl.com/api-reference/multilingual-glossaries.
     */
    public const MAX_TERM_BYTES = 1024;

    public function sanitize(string $term): string
    {
        $term = mb_scrub($term, 'UTF-8');
        $term = (string)preg_replace('/[\x{0000}-\x{001F}\x{0080}-\x{009F}\x{2028}\x{2029}]+/u', ' ', $term);
        $term = (string)preg_replace('/ {2,}/', ' ', $term);

        return (string)preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $term);
    }

    /**
     * Tells whether a cleaned term is longer than DeepL accepts, counted in UTF-8 bytes and not
     * in characters.
     */
    public function exceedsByteLimit(string $term): bool
    {
        return strlen($term) > self::MAX_TERM_BYTES;
    }
}
