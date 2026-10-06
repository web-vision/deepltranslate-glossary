.. include:: /Includes.rst.txt

.. _upgrade60to61:

==================
Upgrade 6.0 to 6.1
==================

Version 6.1 synchronizes glossaries through the DeepL glossary API v3. A glossary
folder is published as one glossary holding a dictionary per language pair, instead
of one glossary per language pair. See
:ref:`breaking-oneglossaryrecordperfolder-1785196802` for the details of the new
structure.

..  important::

    Translations run without a glossary from the deployment of 6.1 until each
    glossary folder has been synchronized again. The glossaries of the API v2 are
    no longer used, and a folder gets its glossary of the API v3 with its first
    synchronization after the upgrade wizard. Run the upgrade wizard and the
    synchronization of every glossary folder in one maintenance window, right
    after the deployment.

Before the update
=================

#.  **Assign the glossary module to every glossary folder.** Version 6.1
    synchronizes and uses only visible folders of type :guilabel:`Folder` with
    the glossary module assigned, which belong to a site. A folder holding terms
    without the glossary module, typically one synchronized through the command
    line only, is neither synchronized nor used for translations anymore, and
    another site never uses it either. Assign the module in the page properties
    of the folder, under
    :guilabel:`Behaviour > Use as Container > Contains Plugin`. See
    :ref:`important-onlyglossaryfoldersaresynchronized-1791313253` and
    :ref:`important-glossariesonlyfromcurrentsite-1791313139`.

#.  **Name the glossaries before they are created.** The name of a glossary is
    chosen once, when the upgrade wizard migrates the records of a folder or the
    first synchronization creates its glossary, and kept afterwards. When
    several installations share the DeepL API key, for example production and
    staging, register a listener of the event
    :php:`\WebVision\Deepltranslate\Glossary\Event\ModifyGlossaryNameEvent`
    before running the wizard, so the glossaries of each installation can be
    told apart in the DeepL account. See
    :ref:`feature-eventtomodifyglossaryname-1791124339`.

Update
======

#.  Update the extension, together with :t3ext:`deepltranslate_core` 6.1:

    ..  code-block:: bash

        composer require -W \
            "web-vision/deepltranslate-core":"~6.1.0" \
            "web-vision/deepltranslate-glossary":"~6.1.0"

#.  Update the database schema, for example with
    :bash:`vendor/bin/typo3 extension:setup`. The upgrade wizard needs the new
    table :sql:`tx_deepltranslate_glossarydictionary`.

#.  Run the upgrade wizard
    :guilabel:`Migrate glossaries to the DeepL glossary API v3`, either in
    :guilabel:`Admin Tools > Upgrade > Upgrade Wizard` or on the command line:

    ..  code-block:: bash

        vendor/bin/typo3 upgrade:run deepltranslateGlossary_migrateToMultilingualGlossary

    The wizard keeps one glossary record per folder and detaches it from the
    glossaries of the API v2. It changes the database only and does not delete
    anything at DeepL.

#.  Synchronize every glossary folder, through the backend or on the command
    line:

    ..  code-block:: bash

        vendor/bin/typo3 deepl:glossary:sync

    Translations use a glossary again once its folder has been synchronized.
    Until the wizard ran, a folder still holding glossaries of the API v2 is not
    synchronized, and the synchronization fails with a message naming the
    wizard.

After the update
================

The glossaries of the API v2 stay on the DeepL account, unused, and count
against its glossary limit. Remove them once every folder has been synchronized:

..  code-block:: bash

    vendor/bin/typo3 deepl:glossary:cleanup --legacy

See :ref:`housekeeping` for the cleanup command. Do not use
:bash:`deepl:glossary:cleanup --all` when other installations or tools share
the API key, it deletes their glossaries as well.
