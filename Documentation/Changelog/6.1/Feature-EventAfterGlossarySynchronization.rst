.. include:: /Includes.rst.txt

..  _feature-eventafterglossarysynchronization-1791321855:

======================================================
Feature: Event after the synchronization of a glossary
======================================================

Description
===========

The PSR-14 event
:php:`\WebVision\Deepltranslate\Glossary\Event\AfterGlossarySynchronizedEvent`
is dispatched after a glossary folder was synchronized with DeepL and the
result was stored in TYPO3.

It is dispatched by every synchronization of a folder, from the backend and
from the ``deepl:glossary:sync`` command, also when the scheduler runs it. A
synchronization which failed or was rejected does not dispatch it, the stored
state of the folder is unchanged then. The listeners run after the lock of the
folder is released, so their work does not keep anybody from synchronizing
the folder.

The event provides the following read-only properties:

:php:`$pageId`
    The page id of the glossary folder.

:php:`$glossaryId`
    The id of the DeepL glossary of the folder. Empty when the folder has no
    glossary.

:php:`$hasGlossary`
    :php:`false` when the folder holds no term, so its glossary was removed at
    DeepL or never created.

:php:`$dictionaries`
    The dictionaries the glossary holds at DeepL after the synchronization,
    one :php:`\DeepL\MultilingualGlossaryDictionaryInfo` per language pair with
    its source language, target language and number of entries. Empty when the
    folder has no glossary.

:php:`$collisions`
    The site languages sharing a glossary language code the site configuration
    does not resolve unambiguously, as
    :php:`\WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollision`,
    see :ref:`important-sharedglossarylanguagecodes-1789729876`. This class
    and its reason
    :php:`\WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollisionReason`
    are no longer internal.

Example
-------

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/FlagSynchronizedGlossaryTerms.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use MyVendor\MyExtension\Domain\Repository\GlossaryTermStateRepository;
    use TYPO3\CMS\Core\Attribute\AsEventListener;
    use WebVision\Deepltranslate\Glossary\Event\AfterGlossarySynchronizedEvent;

    #[AsEventListener('my-extension/flag-synchronized-glossary-terms')]
    final readonly class FlagSynchronizedGlossaryTerms
    {
        public function __construct(
            private GlossaryTermStateRepository $glossaryTermStateRepository,
        ) {
        }

        public function __invoke(AfterGlossarySynchronizedEvent $event): void
        {
            $this->glossaryTermStateRepository->markFolderAsSynchronized(
                $event->pageId,
                $event->hasGlossary
            );
        }
    }

Impact
======

Extensions can react to a synchronized glossary folder, for example to flag
the terms of the folder as published to DeepL, to tell them apart from terms
changed in TYPO3 since.

.. index:: PHP-API, ext:deepltranslate_glossary
