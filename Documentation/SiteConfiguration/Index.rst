..  include:: /Includes.rst.txt

..  _site-configuration:

==================
Site configuration
==================

DeepL glossaries only know base language codes like ``en``, ``pt`` or ``de``,
see the `DeepL glossary documentation
<https://developers.deepl.com/api-reference/multilingual-glossaries>`__.
A glossary with the target language ``EN`` is used for translations into
``EN-GB`` and ``EN-US`` alike, the same applies to ``PT-PT`` and ``PT-BR``.

The glossary language code of a site language is taken from its
:guilabel:`Locale`. When several site languages share a code, for example
English (UK) with ``en_GB`` and English (US) with ``en_US``, only one of
them can provide the terms of that glossary.

..  _site-configuration-glossary-terms:

Glossary terms
==============

The field is shown in the :guilabel:`DeepL Settings` of each site language in
the :guilabel:`Site Management > Sites` module, next to the DeepL target
language.

..  confval:: deeplGlossaryTerms
    :name: site-language-deeplGlossaryTerms
    :type: string
    :default: automatic

    ``automatic``
        The site language provides the glossary terms of its language code
        according to the rules below.

    ``preferred``
        The site language provides the glossary terms of its language code,
        even when another site language sharing the code has terms too.

    ..  code-block:: yaml
        :caption: config/sites/<identifier>/config.yaml

        languages:
          -
            languageId: 1
            title: 'English (UK)'
            locale: en_GB.UTF-8
            deeplTargetLanguage: EN-GB
          -
            languageId: 2
            title: 'English (US)'
            locale: en_US.UTF-8
            deeplTargetLanguage: EN-US
            deeplGlossaryTerms: preferred

..  _site-configuration-glossary-terms-rules:

Which site language provides the terms
======================================

The synchronization decides per glossary language code:

#.  The default language always provides the terms of its own code. A
    site language sharing that code is never used, even when it is marked
    as ``preferred``.
#.  Otherwise the site language marked as ``preferred`` is used. When
    several are marked, the one with the lowest language id wins.
#.  Otherwise the site language with the lowest language id having at least
    one glossary term is used.

Terms are never mixed between site languages. A term missing in the
selected site language is missing in the glossary, it is not taken from
another site language sharing the code.

Example: a site with the default language German and the site languages
English (UK) and English (US) builds one ``de`` to ``en`` glossary. Marking
English (US) as ``preferred`` makes it use the terms of English (US), the
terms of English (UK) are ignored.

..  _site-configuration-glossary-terms-warnings:

Warnings during the synchronization
===================================

The synchronization warns when the site configuration does not decide
unambiguously:

*   several site languages sharing a code have terms and none of them is
    marked as ``preferred``,
*   several site languages sharing a code are marked as ``preferred``,
*   a site language sharing the code of the default language is marked as
    ``preferred``.

The warning is shown as a message after using the synchronize button in the
backend, printed by the :ref:`deepl:glossary:sync <sync-cli>` command and
written to the TYPO3 log. Mark exactly one site language per shared code as
``preferred`` to resolve it.
