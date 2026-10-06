.. include:: /Includes.rst.txt

..  _breaking-oneglossaryrecordperfolder-1785196802:

========================================
Breaking: One glossary record per folder
========================================

Description
===========

The extension synchronizes glossaries through the DeepL glossary API v3
instead of the API v2.

The glossary API v2 only knows glossaries covering a single language pair,
which cannot be changed once created. A glossary folder therefore created one
glossary record, and with it one glossary at DeepL, for every language pair
it contained, and every synchronization deleted and recreated them with a new
glossary id.

The glossary API v3 keeps a persistent glossary holding one dictionary per
language pair, which is edited in place. The structure follows that model:

*   :sql:`tx_deepltranslate_glossary` holds exactly one record per glossary
    folder, carrying the DeepL glossary id and the synchronization state.
*   The new table :sql:`tx_deepltranslate_glossarydictionary` holds one record
    per language pair, with its source and target language, its entry count
    and its synchronization state.

The language pair of a glossary moved to its dictionaries. The extension no
longer writes the columns :sql:`source_lang` and :sql:`target_lang` of
:sql:`tx_deepltranslate_glossary`. It reads them only to find the records of
the API v2 which the upgrade wizard has not migrated yet.

The change is made in a minor version, as both APIs cannot be used side by
side: DeepL states that a glossary edited through the API v3 can no longer be
read correctly through the API v2. Staying with the API v2 until the next
major version would keep the glossary ids changing with every
synchronization and every folder occupying one glossary per language pair of
the DeepL account.

Impact
======

Translations run without a glossary from the deployment of version 6.1 until
each glossary folder has been synchronized again. The glossaries of the API v2
are not used anymore, and a folder gets its glossary of the API v3 with its
first synchronization after the upgrade wizard, see
:ref:`upgrade60to61`.

Custom code reading :sql:`tx_deepltranslate_glossary` to resolve a language
pair no longer finds one glossary record per pair. A glossary folder resolves
to a single glossary id, valid for every language pair its dictionaries list.

A folder occupies a single glossary of the DeepL account, instead of one per
language pair, and its glossary id stays the same across synchronizations.

A failing synchronization is no longer silent. Before, a failed request to
DeepL could leave a glossary record with an empty glossary id and a fresh
synchronization time, so the folder looked synchronized while no glossary
existed at DeepL. The synchronization now fails with the error of DeepL, which
the backend shows as message and the command reports with a failing exit code.

:php:`\WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository`
and
:php:`\WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryEntryRepository`
take their dependencies through their constructors. Creating them with
:php:`new` without arguments fails, get them through dependency injection or
:php:`GeneralUtility::makeInstance()` instead.

The deprecated :php:`DeeplGlossaryService::syncGlossaries()` synchronizes
through the API v3 and throws its exceptions,
:php:`\DeepL\DeepLException`,
:php:`\WebVision\Deepltranslate\Glossary\Exception\GlossaryFolderNotSyncableException` and
:php:`\WebVision\Deepltranslate\Glossary\Exception\GlossarySyncInProgressException`,
instead of
:php:`\WebVision\Deepltranslate\Glossary\Exception\FailedToCreateGlossaryException`.
A folder without any term no longer throws, its glossary is removed from DeepL
instead. See :ref:`deprecation-glossaryapiv2handling-1785196804`.

Affected installations
======================

All installations with synchronized glossaries, custom code querying
:sql:`tx_deepltranslate_glossary` directly, and custom code creating the
repositories of the extension or calling
:php:`DeeplGlossaryService::syncGlossaries()`.

Migration
=========

Update the database schema first, the wizard needs the new table
:sql:`tx_deepltranslate_glossarydictionary`. Then run the upgrade wizard
:guilabel:`Migrate glossaries to the DeepL glossary API v3`, either in
:guilabel:`Admin Tools > Upgrade > Upgrade Wizard` or on the command line:

..  code-block:: bash

    vendor/bin/typo3 extension:setup
    vendor/bin/typo3 upgrade:run deepltranslateGlossary_migrateToMultilingualGlossary

The wizard collapses the existing records of a folder into a single glossary
record. A glossary of the API v2 covers one language pair and cannot become a
dictionary of a multilingual glossary, so the folder gets a new glossary.

The record is detached from its former glossary, so the next synchronization
publishes the folder through the API v3 and creates its dictionaries. Run the
synchronization of every glossary folder after the wizard, either through the
backend or with :bash:`deepl:glossary:sync`.

Until the wizard ran, a folder still holding glossaries of the API v2 is not
synchronized. The synchronization fails with a message naming the wizard, as
the wizard would otherwise detach the glossary published in the meantime.

The wizard migrates locally only and needs no connection to DeepL. The
glossaries of the API v2 stay on the DeepL account, so a copy of the database
sharing the API key, a staging system for example, does not delete the
glossaries the live system still translates with. The wizard lists their ids
and the commands to remove them. Once no other instance uses them, remove all
of them with:

..  code-block:: bash

    vendor/bin/typo3 deepl:glossary:cleanup --legacy

Do not use :bash:`deepl:glossary:cleanup --all` when other instances share the
API key, it removes their glossaries as well.

When upgrading from version 4, run the wizard
:guilabel:`Glossary table migration` first. The wizard waits for it and fails
with a message naming it until the glossaries are copied.

The columns :sql:`source_lang` and :sql:`target_lang` are kept on
:sql:`tx_deepltranslate_glossary` until the wizard has run and are removed
with the next major version.

.. index:: Database, TCA, PHP-API, ext:deepltranslate_glossary, NotScanned
