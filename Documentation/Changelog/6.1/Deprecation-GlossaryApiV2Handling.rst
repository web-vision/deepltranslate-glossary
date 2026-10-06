.. include:: /Includes.rst.txt

..  _deprecation-glossaryapiv2handling-1785196804:

=====================================
Deprecation: Glossary handling for v2
=====================================

Description
===========

Everything handling the DeepL glossary API v2 is deprecated:

*   :php:`\WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService`
*   :php:`\WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV2Client`
*   :php:`\WebVision\Deepltranslate\Glossary\Client\GlossaryAPIV2ClientInterface`
*   :php:`\WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository::getGlossaryInformationForSync()`
*   :php:`\WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository::getGlossaryBySourceAndTargetForSync()`
*   :php:`\WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository::updateLocalGlossary()`
*   :php:`\WebVision\Deepltranslate\Glossary\Exception\FailedToCreateGlossaryException`

The extension synchronizes a glossary folder through
:php:`\WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService::syncGlossary()`
and the glossary API v3 instead. The synchronization command, the
synchronization button of the backend, the listing and the cleanup command all
use the API v3.

Impact
======

The deprecated classes and methods are no longer used by the extension, but
they are still functional. Calling a public method of
:php:`DeeplGlossaryService` or :php:`GlossaryAPIV2Client`, or one of the three
deprecated repository methods, triggers a deprecation notice. The repository
methods still create and update a glossary record per language pair, which
the translation does not use.

DeepL states that a glossary edited through the API v3 can no longer be read
correctly through the API v2, so both should not be mixed on the same
glossary.

:php:`DeeplGlossaryService::syncGlossaries()` changed its behaviour. It
synchronizes the glossary folder through the API v3, exactly like
:php:`MultilingualGlossaryService::syncGlossary()`, and no longer creates a
glossary record and a DeepL glossary per language pair:

*   It throws the exceptions of the API v3 synchronization,
    :php:`\DeepL\DeepLException`,
    :php:`\WebVision\Deepltranslate\Glossary\Exception\GlossaryFolderNotSyncableException`
    and, while the folder is being synchronized already,
    :php:`\WebVision\Deepltranslate\Glossary\Exception\GlossarySyncInProgressException`,
    instead of :php:`FailedToCreateGlossaryException`. Nothing throws
    :php:`FailedToCreateGlossaryException` any longer.
*   A folder without any term no longer throws. Its glossary is removed from
    DeepL and the folder is detached from it.
*   A folder which cannot be synchronized throws
    :php:`GlossaryFolderNotSyncableException`, for example a page which is no
    visible glossary folder of a site, a folder whose terms form no language
    pair DeepL supports, or a folder still holding glossary records of the
    API v2.
*   It returns the site languages sharing a glossary language code which the
    site configuration does not resolve, instead of nothing.

Affected installations
======================

Instances with custom code calling the deprecated classes or methods, or
catching :php:`FailedToCreateGlossaryException`.

Migration
=========

Use :php:`MultilingualGlossaryService::syncGlossary()` to synchronize a
glossary folder, and catch :php:`\DeepL\DeepLException` and
:php:`GlossaryFolderNotSyncableException` instead of
:php:`FailedToCreateGlossaryException`.

There is no public replacement for accessing the glossary API directly, for
example to list, create or delete glossaries. The client of the API v3 used by
the extension is internal. Use the DeepL PHP library
`deeplcom/deepl-php <https://github.com/DeepLcom/deepl-php>`__ for such
access.

There is no fallback to the API v2, fresh installations always use the API v3.

.. index:: PHP-API, ext:deepltranslate_glossary, NotScanned
