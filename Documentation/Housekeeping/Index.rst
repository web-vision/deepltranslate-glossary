..  _housekeeping:

Housekeeping
============

Three CLI commands are available for Cleanup, Sync and Overview.

Overview
--------

To get an overview of how many glossaries are registered with DeepL, you can use
the following:

..  code-block:: bash

    vendor/bin/typo3 deepl:glossary:list

This will give you an overview of the API connected glossaries with their DeepL ID,
name, dictionaries and creation date. Each dictionary is listed with its language pair
and number of entries.

..  code-block:: bash

    vendor/bin/typo3 deepl:glossary:list 123-123

Given a glossary ID, the command lists the entries of every dictionary of that
glossary.

Cleanup
-------

The cleanup command removes glossaries from DeepL, for example glossaries the
upgrade wizard could not remove while migrating to the glossary API v3.

..  code-block:: bash

    vendor/bin/typo3 deepl:glossary:cleanup --all

..  code-block:: bash

    vendor/bin/typo3 deepl:glossary:cleanup --glossaryId 123-123

This command deletes all glossaries or one glossary registered in the DeepL API. In
addition, each glossary ID is checked against the database and if found, the database
record is detached from it and the dictionaries describing the deleted glossary are
removed with it. The next synchronisation publishes the folder as a new glossary.

..  warning::

    `--all` deletes every glossary of the DeepL account. When several instances
    share the API key, the glossaries of the other instances are deleted as
    well. Use `--glossaryId` in that case.

At the end you will get a table with all glossary IDs, telling whether DeepL deleted
the glossary and whether a database record has been detached from it. A glossary
DeepL refuses to delete, for example because of its rate limit, does not stop the
others from being deleted. Its record keeps pointing at it, and the command reports
the failure and ends with a failing exit code, so it can be run again.

..  code-block:: bash

    vendor/bin/typo3 deepl:glossary:cleanup --notinsync

With `--notinsync`, the command compares the glossary records with the glossaries
of the DeepL account. A glossary record pointing at a glossary the DeepL account no
longer contains loses its sync information, so the next synchronization publishes the
folder again. Nothing is deleted from DeepL, so instances sharing the API key are not
affected.

When DeepL lists no glossary at all, nothing is detached and the command shows a
warning. Check the configured API key in that case. When the list cannot be fetched,
the command fails without detaching anything.

This command does not delete your glossaries in TYPO3.

..  _sync-cli:

Synchronisation
---------------

Synchronisation is performed by CLI command or as a scheduled task (as configured
CLI command).

..  code-block:: bash

    vendor/bin/typo3 deepl:glossary:sync

Accepts pageId as option. If not given, syncs every visible glossary folder in the
default language.

A failing folder does not stop the others from being synchronised. Every failure is
reported at the end with the page id, and the command ends with a failing exit code.
A folder outside any site, a folder whose terms form no language pair and a folder
still holding glossaries of the glossary API v2 fail this way. The latter keeps failing
until the upgrade wizard ran, see :ref:`upgrade60to61`.

When DeepL refuses the API key or the quota of the account is exceeded, the command
stops at the folder where it happened and reports how many folders were left out, as
every remaining folder would fail the same way. DeepL reports an exceeded quota as well
when the account holds its maximum number of glossaries, glossaries of other
installations sharing the API key included. Check the usage of the account before
looking for a billing problem.

The command also reports, without failing:

*   a folder holding no terms. It has no glossary at DeepL, a glossary published
    for it before has been removed.
*   a folder skipped because another process is synchronising it, for example an
    editor while the scheduler task runs. The next run synchronises it.
*   that no glossary folder exists at all.

Site languages sharing a glossary language code the site configuration does
not decide unambiguously are reported as warnings, see
:ref:`site-configuration-glossary-terms-warnings`.
