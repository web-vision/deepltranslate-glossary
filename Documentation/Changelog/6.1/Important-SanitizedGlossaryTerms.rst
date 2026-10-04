.. include:: /Includes.rst.txt

..  _important-sanitizedglossaryterms-1785196803:

=====================================
Important: Glossary terms are cleaned
=====================================

Description
===========

Glossary terms are cleaned before they are sent to DeepL. Control characters,
such as a tab or a line break, and the Unicode line and paragraph separators
are replaced by a space, repeated spaces are reduced to one, and the term is
trimmed, leading and trailing Unicode spaces such as a non-breaking space
included. A term pair is dropped when its source or its target is empty
afterwards.

DeepL rejects a term without non-whitespace characters or with one of these
characters, and answers the whole request with an error. A single term an
editor left unfilled, or a term taken over from an import, would therefore
abort the synchronization of an entire glossary folder.

Impact
======

Terms which differ in whitespace or control characters only are treated as the
same term. DeepL accepts a source term once per language pair, so the pair of
the oldest glossary entry wins, and every skipped duplicate is logged as a
warning.

A glossary folder synchronizes successfully even when single term pairs are
incomplete. Those pairs are skipped silently instead of failing the folder.

When the terms of a folder form no complete pair at all, for example because
the folder is no longer translated, the synchronization fails with a message,
and translations keep using the glossary synchronized before. Only a folder
without any term removes its glossary from DeepL.

Affected installations
======================

Instances with glossary entries containing leading or trailing whitespace,
control characters, or with incomplete or duplicate term pairs.

Migration
=========

No migration required. Synchronize the affected glossary folders to send the
cleaned terms to DeepL.

.. index:: PHP-API, ext:deepltranslate_glossary
