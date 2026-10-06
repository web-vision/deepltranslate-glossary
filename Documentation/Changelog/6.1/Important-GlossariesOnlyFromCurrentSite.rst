.. include:: /Includes.rst.txt

..  _important-glossariesonlyfromcurrentsite-1791313139:

==================================================
Important: Glossaries are taken from the same site
==================================================

Description
===========

A page is translated with a glossary of its own site only.

Before, a site without a glossary folder of its own was translated with any
glossary of the installation for the language pair, also with one of another
site. With several glossaries to choose from, the database order decided
which one was used.

The glossary of a translation is now taken from the glossary folders of the
site of the translated page only: visible folders with the glossary module
assigned. Glossaries of other sites are never used, and neither are glossaries
stored in a folder without the glossary module, for example one synchronized
through the command line before, as such a folder cannot be synchronized
anymore, see :ref:`important-onlyglossaryfoldersaresynchronized-1791313253`.
When several glossaries qualify, the one with the lowest uid is used.

Impact
======

Pages of a site without a glossary folder of its own are translated without
a glossary, where they used the glossary of another site, or of a folder of
their site without the glossary module, before.

Affected installations
======================

Installations with several sites sharing the glossary folder of one of them,
and installations keeping glossaries in a folder without the glossary module.

Migration
=========

Create a glossary folder in every site that should use a glossary, or assign
the glossary module to the folder holding the terms, see :ref:`glossaries`,
and synchronize it.

.. index:: Backend, ext:deepltranslate_glossary
