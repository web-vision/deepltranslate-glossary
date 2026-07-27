.. include:: /Includes.rst.txt

..  _deprecation-glossaryapiv2handling-1785196804:

======================================
Deprecation: Glossary handling for v2
======================================

Description
===========

Everything handling the DeepL glossary API v2 is deprecated:

*   :php:`\WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService`
*   :php:`\WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV2Client`
*   :php:`\WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV2ClientInterface`

The extension synchronizes through
:php:`\WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService` and the glossary
API v3 instead. The synchronization command, the synchronization button of the
backend, the listing and the cleanup command all use the API v3 now.

Impact
======

The deprecated classes are not used by the extension any longer, but they are still
functional. DeepL states that a glossary edited through the API v3 can no longer be
queried correctly through the API v2, so both should not be mixed on the same glossary.

Affected installations
======================

Instances with custom code calling the deprecated classes.

Migration
=========

Use :php:`MultilingualGlossaryService::syncGlossary()` to synchronize a glossary folder and
:php:`GlossaryAPIV3ClientInterface` to talk to the glossary API. There is no fallback to the
API v2, fresh installations always use the API v3.

.. index:: PHP-API, ext:deepltranslate_glossary, NotScanned
