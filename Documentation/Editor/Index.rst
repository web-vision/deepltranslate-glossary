..  _glossaries:

Glossaries
==========

You can define glossaries for your translations. A glossary folder holds exactly one
glossary, which is registered once at DeepL and kept up to date afterwards. Its name is
made up of the page title and the page id, and it is chosen at the first synchronisation,
so renaming the folder later does not rename the glossary.

The glossary contains one dictionary per language pair, listing how many terms that pair
covers. So a single glossary serves every language combination its folder provides.

..  figure:: /Images/Editor/glossaries-list-view.png
    :alt: List view of glossary items

On pages with Doktype 254 (Folder) and "Use Container" set to "DeepL Glossary",
a synchronise button appears for easy synchronisation of the glossary terms listed in this page.

A glossary folder serves the pages of its own site only. Every site that should be
translated with a glossary needs a glossary folder of its own.

Language pairs
--------------

The glossary covers every pair of the site languages the folder is translated into,
as long as DeepL supports glossaries for that pair. A term and its translations form a
pair through the term in the default language, so translate a term with the TYPO3
translation of the record. A term created directly in another language, without a term
in the default language, is not paired reliably, see :ref:`Known Issues <knownIssues>`.

The default language of the site is used as source language only.

Each glossary shows you the current sync status to the DeepL API in the page settings.

..  figure:: /Images/Editor/glossary-sync-tab-not-synced.png
    :alt: Page settings tab *DeepL Translate* with not synced glossary

Adding terms is done through the list module. A term may be up to 1024 characters
long. DeepL counts this limit in bytes, not characters, so a term containing
umlauts, CJK characters or emoji reaches the limit earlier than 1024 characters.
An umlaut takes two bytes, a CJK character three and an emoji four. Saving a
term that exceeds 1024 UTF-8 bytes is rejected with an error message: a new
entry is not created, an existing entry keeps its previous term.

..  figure:: /Images/Editor/glossary-add-term.png
    :alt: Add entry via add record

To generate a glossary translation, simply translate the page into the required
glossary language using the TYPO3 translation dropdown. A custom translation dropdown
will be displayed, which will only accept languages in which glossary entries can be created.

..  figure:: /Images/Editor/glossary-page-translation.png
    :alt: Custom translation dropdown in list view

..  figure:: /Images/Editor/glossary-page-translation-select.png
    :alt: Possible translations based on source language EN

..  note::

    DeepL glossaries only know base language codes. Site languages sharing a
    code, like English (UK) and English (US), feed one common glossary, and
    only one of them provides its terms. The site configuration decides which
    one, see :ref:`site-configuration`.

After that you can make translations with the *Translate to* button.
As the glossary entries are made for not using DeepL standard wording, the
ability of translating entries by DeepL is disabled.

..  figure:: /Images/Editor/glossary-entry-list.png
    :alt: Backend list view of glossary entries, original language english, translated to German

Synchronisation
---------------

You can retrieve the current sync information of this glossary to the API in the
page settings, tab **DeepL Translate**.

..  note::

    No synchronisation is performed on save. Save the terms, then synchronise the
    folder with the button or the :ref:`synchronisation command <sync-cli>`.

    Every change of the terms of a folder marks its glossary as out of sync:
    adding, editing, hiding, deleting, translating, moving or copying a term.
    Translations keep using the glossary in its last synchronized state until
    the folder is synchronized again.

In each glossary directory, a button is enabled to synchronise that glossary.

After sync the tab *DeepL Translate* should look like this:

..  figure:: /Images/Editor/glossary-sync-tab-synced.png
    :alt: Tab "DeepL Translate" with set ID, time of last sync and ready status

Changing a term marks the dictionaries of the glossary as out of sync, shown by the
field :guilabel:`In sync with TYPO3` of each dictionary. Translations keep using the
glossary in its last synchronised state until the folder is synchronised again.

The synchronisation reports its outcome as a message:

*   The glossary is ready to use, with one dictionary per language pair.
*   The folder holds no terms any more. Its glossary is removed from DeepL, and
    translations no longer use a glossary of this folder.
*   The terms of the folder form no language pair DeepL supports, for example when
    the folder or its terms are not translated. The glossary is kept as it is.
*   The folder still holds glossaries of the DeepL glossary API v2. An administrator
    has to run the upgrade wizard first, see :ref:`upgrade60to61`.
*   DeepL could not be reached or refused the request. Check the API key and the
    system log.

Only a visible folder in the default language is synchronised. Synchronising requires
the permission :guilabel:`Allowed Glossary Sync`, edit access to the folder and the
right to edit glossary terms.
