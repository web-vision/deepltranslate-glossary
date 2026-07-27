.. include:: /Includes.rst.txt

..  _important-glossaryoutofsynconeverytermchange-1791313353:

================================================
Important: Every term change outdates a glossary
================================================

Description
===========

Only editing a glossary term marked the glossaries of its folder as out of
sync. Adding, hiding, deleting, translating, moving or copying a term left
them marked as ready, so translations kept using DeepL glossaries which no
longer matched the terms of the folder.

Every change of the terms of a glossary folder now marks its glossaries as
out of sync:

*   adding, editing, hiding or showing a term again,
*   deleting a term,
*   translating a term,
*   moving a term, which affects the folder it leaves and the folder it
    enters,
*   copying a term, which affects the folder receiving the copy.

Saving a term without changing it leaves the glossaries as they are.

Impact
======

A glossary marked as out of sync is not used for translations until it is
synchronized again. After such a term change, translations run without the
glossary of the folder until the folder is synchronized, instead of with a
glossary missing the change.

Affected installations
======================

All installations maintaining glossary terms in the backend.

Migration
=========

None. Synchronize a glossary folder after changing its terms, as before.

.. index:: Backend, ext:deepltranslate_glossary
