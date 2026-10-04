.. include:: /Includes.rst.txt

.. _upgrade60to61:

==================
Upgrade 6.0 to 6.1
==================

Version 6.1 synchronizes glossaries through the DeepL glossary API v3. A glossary
folder is published as one glossary holding a dictionary per language pair, instead
of one glossary per language pair.

#.  Update the extension, together with
    :t3ext:`deepltranslate_core` 6.1.

#.  Update the database schema, for example with
    :bash:`vendor/bin/typo3 extension:setup`. The upgrade wizard needs the new
    table :sql:`tx_deepltranslate_glossarydictionary`.

#.  Run the upgrade wizard
    :guilabel:`Migrate glossaries to the DeepL glossary API v3`, either in
    :guilabel:`Admin Tools > Upgrade > Upgrade Wizard` or on the command line:

    ..  code-block:: bash

        vendor/bin/typo3 upgrade:run deepltranslateGlossary_migrateToMultilingualGlossary

    The wizard keeps one glossary record per folder and removes the glossaries of
    the API v2 from the DeepL account. A glossary it could not remove is logged with
    its id, remove it with
    :bash:`vendor/bin/typo3 deepl:glossary:cleanup --glossaryId <id>`.

#.  Synchronize every glossary folder, through the backend or with
    :bash:`vendor/bin/typo3 deepl:glossary:sync`. Translations use a glossary
    again once its folder has been synchronized.

Until the wizard ran, a folder still holding glossaries of the API v2 is not
synchronized, and the synchronization fails with a message naming the wizard.

See :ref:`breaking-oneglossaryrecordperfolder-1785196802` for the details of the
new structure.

.. index:: ext:deepltranslate_glossary, NotScanned
