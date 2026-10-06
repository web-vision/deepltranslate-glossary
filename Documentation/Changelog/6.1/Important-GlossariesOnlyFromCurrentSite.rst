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

The glossary of a translation is now looked up in this order:

#.  The glossary folders of the site of the translated page.
#.  Without one, the other pages of that site still holding glossaries, for
    example a folder synchronized through the command line without the
    glossary module assigned.

Glossaries of other sites are never used. When several glossaries qualify,
the one with the lowest uid is used.

Impact
======

Pages of a site without a glossary folder of its own are translated without
a glossary, where they used the glossary of another site before.

Affected installations
======================

Installations with several sites sharing the glossary folder of one of them.

Migration
=========

Create a glossary folder in every site that should use a glossary, see
:ref:`glossaries`, and synchronize it.

.. index:: Backend, ext:deepltranslate_glossary
