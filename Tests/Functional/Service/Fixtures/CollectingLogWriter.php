<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Functional\Service\Fixtures;

use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Log\Writer\AbstractWriter;
use TYPO3\CMS\Core\Log\Writer\WriterInterface;

/**
 * Collects the records written to a logger, to see what a service logs.
 */
final class CollectingLogWriter extends AbstractWriter
{
    /**
     * @var list<LogRecord>
     */
    public array $records = [];

    public function writeLog(LogRecord $record): WriterInterface
    {
        $this->records[] = $record;

        return $this;
    }
}
