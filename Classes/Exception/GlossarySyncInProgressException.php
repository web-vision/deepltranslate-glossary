<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Exception;

/**
 * Thrown when a glossary folder is asked to be synchronised while another process synchronises
 * it, for example the scheduler while an editor clicks the button. The folder is fine, the
 * synchronisation can be repeated once the running one has finished.
 */
final class GlossarySyncInProgressException extends \Exception
{
}
