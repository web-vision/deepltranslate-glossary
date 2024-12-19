<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\EventListener;

use WebVision\Deepltranslate\Core\Event\DisallowTableFromDeeplTranslateEvent;

final class ExcludeGlossaryTablesFromDeeplTranslation
{
    public function __invoke(DisallowTableFromDeeplTranslateEvent $event): void
    {
        if (
            $event->tableName === 'tx_deepltranslate_glossaryentry'
            || $event->tableName === 'tx_deepltranslate_glossary'
        ) {
            $event->disallowTranslateButtons();
        }
    }
}
