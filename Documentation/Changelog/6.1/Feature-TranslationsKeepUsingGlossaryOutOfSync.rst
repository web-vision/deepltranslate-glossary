.. include:: /Includes.rst.txt

..  _feature-translationskeepusingglossaryoutofsync-1791124049:

=======================================================
Feature: Translations keep using a glossary out of sync
=======================================================

Description
===========

Changing a glossary term no longer switches the glossary of its folder off.
Adding, editing, translating, deleting, moving or copying a term marks the
dictionaries of the glossary as out of sync, while translations keep using
the glossary in its last synchronized state until the folder is synchronized
again.

Before, a single changed term disabled the glossary of the whole folder, and
every translation ran without it until someone synchronized the folder.

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

Instances editing glossary terms without synchronizing the glossary folder
right afterwards.

Migration
=========

No migration required. Synchronize the glossary folder once to apply the
pending term changes.

.. index:: Backend, ext:deepltranslate_glossary
