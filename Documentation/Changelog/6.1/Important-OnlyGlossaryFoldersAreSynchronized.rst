.. include:: /Includes.rst.txt

..  _important-onlyglossaryfoldersaresynchronized-1791313253:

=================================================
Important: Only glossary folders are synchronized
=================================================

Description
===========

The backend synchronization of a glossary accepts only a folder set up as
glossary: a page of type :guilabel:`Folder` with the glossary module assigned.
Before, the check let every folder pass, with or without the glossary module,
and every page with the glossary module, whatever its type.

The synchronization button follows the same rule. It is no longer shown on a
page with the glossary module which is not a folder.

The command :bash:`vendor/bin/typo3 deepl:glossary:sync` is not changed by
this.

Impact
======

Synchronizing a page which is not a glossary folder, or a page which does not
exist, fails with a message naming the page. No glossary is created for it.

Affected installations
======================

Installations keeping glossary terms on a page with the glossary module
assigned which is not a folder.

Migration
=========

Change the type of the page to :guilabel:`Folder`, keep the glossary module
assigned under :guilabel:`Behaviour > Use as Container > Contains Plugin`,
then synchronize it again.

.. index:: Backend, ext:deepltranslate_glossary
