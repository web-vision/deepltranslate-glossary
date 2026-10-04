.. include:: /Includes.rst.txt

..  _breaking-removedglossarybackendutility-1791118612:

========================================
Breaking: Removed GlossaryBackendUtility
========================================

Description
===========

The class
:php:`\WebVision\Deepltranslate\Glossary\Utility\GlossaryBackendUtility`
has been removed. Its only method :php:`checkGlossaryCanCreated()` filtered
the languages of the :guilabel:`Translate with DeepL` dropdown in a glossary
folder. The dropdown is no longer rendered in glossary folders, so the method
was not called anywhere anymore.

The page TSconfig registering
:file:`EXT:deepltranslate_glossary/Resources/Private/Backend/` as additional
backend template path has been removed together with the only partial it
provided, :file:`Partials/AdditionalTranslation/Glossary.html`. No backend
template renders that partial.

Impact
======

Calling :php:`GlossaryBackendUtility::checkGlossaryCanCreated()` results in a
fatal error.

Affected installations
======================

Instances with custom code calling
:php:`GlossaryBackendUtility::checkGlossaryCanCreated()`, or overriding the
partial :file:`Partials/AdditionalTranslation/Glossary.html` of this
extension.

Migration
=========

There is no replacement. Remove calls to the method and overrides of the
partial.

.. index:: Backend, PHP-API, TSConfig, ext:deepltranslate_glossary, NotScanned
