<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use WebVision\Deepltranslate\Core\Event\DisallowTableFromDeeplTranslateEvent;

/**
 * Listen to PSR-14 event used for TYPO3 v13 and v14 to disallow translating
 * glossaries and glossary items with DeepL translate, which does not make
 * any sense at all.
 *
 * Filtering is based on {@see DisallowTableFromDeeplTranslateEvent::$tableName}.
 */
#[Autoconfigure(public: true)]
final class ExcludeGlossaryTablesFromDeeplTranslation
{
    #[AsEventListener(identifier: 'deepltranslate-glossary/disallow-entries')]
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
