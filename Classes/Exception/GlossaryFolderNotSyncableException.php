<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Exception;

/**
 * Thrown when a page is asked to be synchronised which cannot become a DeepL glossary, for
 * example a folder not set up as glossary or a glossary folder outside any site.
 */
final class GlossaryFolderNotSyncableException extends \Exception
{
}
