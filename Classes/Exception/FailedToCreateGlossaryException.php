<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Exception;

use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;

/**
 * @deprecated since 6.1, will be removed in 7.0. Nothing throws it any longer, the
 *             synchronisation through {@see MultilingualGlossaryService::syncGlossary()} throws
 *             {@see GlossaryFolderNotSyncableException} or a DeepL exception instead.
 */
final class FailedToCreateGlossaryException extends \Exception
{
}
