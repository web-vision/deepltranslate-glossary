.. include:: /Includes.rst.txt

..  _breaking-oneglossaryrecordperfolder-1785196802:

========================================
Breaking: One glossary record per folder
========================================

Description
===========

The DeepL glossary API v2 could only store a single language pair per
glossary, so a glossary folder created one glossary record, and with it one
remote glossary, for every language pair it contained.

The glossary API v3 keeps a persistent glossary holding one dictionary per
language pair. The structure follows that model:

*   :sql:`tx_deepltranslate_glossary` holds exactly one record per glossary
    folder, carrying the DeepL glossary id and the synchronization state
*   the new table :sql:`tx_deepltranslate_glossarydictionary` holds one record
    per language pair with its entry count and synchronization state

The columns :sql:`source_lang` and :sql:`target_lang` therefore no longer
describe a glossary but a dictionary.

Impact
======

Custom code reading :sql:`tx_deepltranslate_glossary` to resolve a language
pair no longer finds one glossary record per pair. A glossary folder now
resolves to a single glossary id which is valid for every language pair the
glossary contains.

Reducing a folder to one remote glossary also lowers the number of glossaries
counted against the limit of the DeepL account.

Affected installations
======================

Instances with synchronized glossaries, and any custom code querying
:sql:`tx_deepltranslate_glossary` directly.

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

.. index:: Database, TCA, ext:deepltranslate_glossary, NotScanned
