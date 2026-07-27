.. include:: /Includes.rst.txt

..  _important-sanitizedglossaryterms-1785196803:

=====================================
Important: Glossary terms are cleaned
=====================================

Description
===========

Glossary terms are trimmed before they are sent to DeepL, and a term pair is
dropped when its source or its target is empty afterwards.

DeepL rejects a term without non-whitespace characters and answers the whole
request with an error. A single term an editor left unfilled would therefore
abort the synchronization of an entire glossary folder.

Impact
======

Surrounding whitespace of a term no longer reaches DeepL, so terms which
differ in whitespace only are treated as the same term. Where trimming lets
two source terms collapse into the same term, the last pair wins.

A glossary folder synchronizes successfully even when single term pairs are
incomplete. Those pairs are skipped silently instead of failing the folder.

Affected installations
======================

Instances with glossary entries containing leading or trailing whitespace, or
with incomplete term pairs.

Migration
=========

No migration required. Synchronize the affected glossary folders to send the
cleaned terms to DeepL.

.. index:: PHP-API, ext:deepltranslate_glossary
