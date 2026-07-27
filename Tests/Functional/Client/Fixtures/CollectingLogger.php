<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Client\Fixtures;

use Psr\Log\AbstractLogger;

/**
 * Collects the level of every log entry, to see what a client logs.
 */
final class CollectingLogger extends AbstractLogger
{
    /**
     * @var list<mixed>
     */
    public array $levels = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->levels[] = $level;
    }
}
