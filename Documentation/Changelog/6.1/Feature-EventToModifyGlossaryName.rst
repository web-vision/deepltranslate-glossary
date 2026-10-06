.. include:: /Includes.rst.txt

..  _feature-eventtomodifyglossaryname-1791124339:

===============================================
Feature: Event to modify the name of a glossary
===============================================

Description
===========

The PSR-14 event
:php:`\WebVision\Deepltranslate\Glossary\Event\ModifyGlossaryNameEvent`
allows changing the name a glossary folder is published under at DeepL.

By default, a glossary is named after its folder, followed by the page id of
the folder, for example ``Glossary [12]``. A folder without a title is named
``Glossary`` followed by its page id.

The event is dispatched when the glossary record of a folder is created by its
first synchronization, and when the upgrade wizard migrates the glossary
records of a folder. A listener therefore runs in the backend, on the command
line and in the install tool. It cannot rely on a request, a backend user or
a frontend context, and gets everything it needs from the event, the page id
of the folder above all.

It provides the following properties:

:php:`$pageId`
    The page id of the glossary folder, read-only.

:php:`$folderTitle`
    The title of the glossary folder, read-only. Empty for a folder without a
    title.

:php:`$glossaryName`
    The name the glossary is published under. An empty name falls back to the
    default name, as DeepL refuses a glossary without one. A name longer than
    255 characters is cut to 255 characters.

Example
-------

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/PrefixGlossaryName.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use TYPO3\CMS\Core\Attribute\AsEventListener;
    use WebVision\Deepltranslate\Glossary\Event\ModifyGlossaryNameEvent;

    #[AsEventListener('my-extension/prefix-glossary-name')]
    final class PrefixGlossaryName
    {
        public function __invoke(ModifyGlossaryNameEvent $event): void
        {
            $event->glossaryName = 'ACME ' . $event->glossaryName;
        }
    }

Impact
======

Instances sharing a DeepL API key can tell their glossaries apart in the
DeepL account, for example by prefixing the name of the instance.

The name of a glossary record which already exists is kept, including when its
folder is synchronized again.

.. index:: PHP-API, ext:deepltranslate_glossary
