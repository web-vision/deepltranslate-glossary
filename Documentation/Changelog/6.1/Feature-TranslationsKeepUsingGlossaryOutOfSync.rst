.. include:: /Includes.rst.txt

..  _feature-translationskeepusingglossaryoutofsync-1791124049:

=======================================================
Feature: Translations keep using a glossary out of sync
=======================================================

Description
===========

Only editing a glossary term marked the glossary of its folder as out of
sync. Adding, hiding, deleting, translating, moving or copying a term left it
marked as ready, so the backend claimed a glossary to be in sync which no
longer matched the terms of the folder. Where a change did mark the glossary,
the glossary of the whole folder was switched off, and every translation ran
without it until someone synchronized the folder.

Every change of the terms of a glossary folder now marks the dictionaries of
its glossary as out of sync:

*   adding, editing, hiding or showing a term again,
*   deleting a term,
*   translating a term,
*   moving a term, which affects the folder it leaves and the folder it
    enters,
*   copying a term, which affects the folder receiving the copy.

Saving a term without changing it leaves the glossary as it is.

Translations keep using the glossary in its last synchronized state until the
folder is synchronized again.

Where several glossary folders of a site cover the same language pair, a
glossary not ready to use no longer hides a ready one.

Impact
======

A glossary update does not break translations. The terms changed since the
last synchronization reach DeepL with the next synchronization of the folder.

The field :guilabel:`DeepL marked glossary as ready to use` of the glossary
record stays set when a term changes. The out-of-sync state is shown by the
field :guilabel:`In sync with TYPO3` of each dictionary.

Affected installations
======================

All installations maintaining glossary terms in the backend, in particular
instances editing glossary terms without synchronizing the glossary folder
right afterwards.

Migration
=========

No migration required. Synchronize a glossary folder after changing its terms
to apply the pending changes.

.. index:: Backend, ext:deepltranslate_glossary
