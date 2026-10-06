.. include:: /Includes.rst.txt

..  _important-copiedglossaryfolderstartswithoutglossary-1791322314:

===========================================================
Important: A copied glossary folder starts without glossary
===========================================================

Description
===========

Copying a glossary folder copies its terms, but not its glossary. The glossary
record of the folder and its dictionaries are no longer copied along with the
page, so the copy starts without a glossary at DeepL. Its first
synchronization creates a glossary of its own, named after the copy.

Before, the copy took over the glossary record of the original folder,
including its DeepL glossary id. Synchronizing the copy then changed or
deleted the glossary of the original folder.

Impact
======

A copied glossary folder is not used for translations until it has been
synchronized. The glossary of the original folder is not affected by the
copy.

Affected installations
======================

Installations copying glossary folders, for example to set up the glossary of
a new site.

Migration
=========

Synchronize a copied glossary folder before translating with it. A copy made
before this change still points at the glossary of its original. Delete and
recreate the glossary record of such a copy, or create a new glossary folder
for it.

.. index:: Backend, ext:deepltranslate_glossary
