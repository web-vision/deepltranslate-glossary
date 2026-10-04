.. include:: /Includes.rst.txt

..  _important-onlyglossaryfoldersaresynchronized-1791313253:

=================================================
Important: Only glossary folders are synchronized
=================================================

Description
===========

A page is synchronized to DeepL only when it is a visible folder set up as
glossary, a page of type :guilabel:`Folder` with the glossary module
assigned, and belongs to a site. The backend synchronization button and the
command :bash:`vendor/bin/typo3 deepl:glossary:sync` apply the same check.
The glossary of a hidden folder is neither synchronized nor used for
translations.

Before, the backend check let every folder pass, with or without the glossary
module, and every page with the glossary module, whatever its type. The
command synchronized any page passed with :bash:`--pageId`.

The synchronization button is no longer shown on a page with the glossary
module which is not a folder.

Translating uses the glossaries of glossary folders only, see
:ref:`important-glossariesonlyfromcurrentsite-1791313139`. Before, a site
without a glossary folder used the glossary of any folder of the site.

A failing folder no longer stops the command. The remaining folders are
synchronized, and every failure is reported at the end with a failing exit
code. A term translated into a language that has been removed from the site
configuration is skipped instead of failing the folder.

A folder is synchronized by one process at a time. A synchronization started
while the folder is being synchronized, for example by a scheduler task while
an editor clicks the button, fails with a message and can be repeated once the
running one has finished. The lock applies per server, so it does not prevent
two synchronizations started on different servers.

Impact
======

Synchronizing a page which is not a glossary folder, a page which does not
exist, or a glossary folder outside any site fails with a message naming the
page. No glossary is created for it.

A glossary synchronized earlier from a folder without the glossary module
assigned is no longer applied to translations, as it could not be kept up to
date anymore.

Affected installations
======================

Installations keeping glossary terms on a page with the glossary module
assigned which is not a folder, and instances keeping glossary terms in a
folder without the glossary module assigned, typically synchronized through
the command line.

Migration
=========

Change the type of the page to :guilabel:`Folder` and assign the glossary
module to it in its page properties under
:guilabel:`Behaviour > Use as Container > Contains Plugin`, then synchronize
it again.

.. index:: CLI, Backend, Frontend, ext:deepltranslate_glossary
