..  _feature-increasedglossarytermlength-1789722086:

=================================================
Feature: Glossary terms may exceed 255 characters
=================================================

Description
===========

A glossary term was limited to 255 characters by its database column only,
while DeepL accepts much longer glossary entries.

The column :sql:`tx_deepltranslate_glossaryentry.term` now holds up to
1024 characters, and the backend form limits the term to 1024 characters
as well, on every database system alike.

DeepL limits each source and target text of a glossary entry to 1024 UTF-8
**bytes**, not characters, see the `DeepL API reference
<https://developers.deepl.com/api-reference/multilingual-glossaries>`__.
A term consisting of plain ASCII characters can use the full 1024
characters, while a term containing multibyte characters, for example
umlauts, CJK characters or emoji, reaches the DeepL limit earlier. TYPO3's
character-based :php:`max` alone cannot catch that, so a dedicated
DataHandler hook rejects saving a term that exceeds 1024 UTF-8 bytes,
with an error message shown to the editor. The term is not stored, and it
is not silently cut at a byte boundary either, since that risks splitting
a multibyte character.

As defense in depth, a synchronization still drops any oversized pair it
encounters instead of aborting the whole glossary, and logs it.

Impact
======

Editors can enter glossary terms longer than 255 characters, and a term that
would exceed DeepL's 1024 byte limit is now rejected on save with a clear
error message, instead of being stored and rejected later by DeepL.

Affected installations
======================

All installations updating :guilabel:`web-vision/deepltranslate-glossary`.

Migration
=========

Update the database schema after updating the extension, either with
:guilabel:`Admin Tools > Maintenance > Analyze Database Structure` or on the
command line:

..  code-block:: bash

    vendor/bin/typo3 extension:setup

Existing terms are kept unchanged, the column is only widened.

.. index:: TCA, Database, ext:deepltranslate_glossary
