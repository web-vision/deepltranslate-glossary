.. include:: /Includes.rst.txt

..  _feature-completedglossaryapiv3client-1785196801:

===============================================
Feature: Completed glossary client for DeepL v3
===============================================

Description
===========

The client for the DeepL glossary API v3 covered creating, reading and merging
glossaries only. The remaining endpoints of the multilingual glossary API are
now available as well:

*   replacing a single dictionary of a language pair
*   removing a single dictionary from a glossary
*   removing a whole glossary
*   reading the entries of a dictionary

Replacing a dictionary uses the replacing endpoint instead of the merging one.
A term removed in TYPO3 would otherwise remain in the DeepL dictionary,
because merging only ever adds or overwrites single terms.

A failing API call no longer answers with a placeholder glossary carrying an
empty glossary id. The original DeepL exception is rethrown instead, keeping
its subclasses intact so that a glossary removed on the DeepL side stays
distinguishable from other failures.

Impact
======

A failed glossary API call is no longer indistinguishable from a successful
one. Previously a caller could store an empty glossary id together with a
fresh synchronization timestamp, which made a glossary folder look
synchronized while no glossary existed at DeepL.

Affected installations
======================

Instances using :guilabel:`EXT:deepltranslate_glossary` with a configured
DeepL API key. The client is marked internal and not part of the public
extension API, so no custom code is expected to call it directly.

Migration
=========

No migration required. Custom code calling the internal client has to catch
:php:`\DeepL\DeepLException` where it previously checked the returned glossary
for an empty glossary id.

.. index:: PHP-API, ext:deepltranslate_glossary
