..  _important-sharedglossarylanguagecodes-1789729876:

==========================================================
Important: Site languages sharing a glossary language code
==========================================================

Description
===========

DeepL glossaries only know base language codes like ``en`` or ``pt``. Site
languages sharing such a code, for example ``de_DE`` and ``de_AT`` or
``en_GB`` and ``en_US``, overwrote each other during the synchronization.
Which one won depended on the database order, and the terms of the default
language could be replaced by the terms of another site language, so terms
vanished from the glossaries sent to DeepL.

The synchronization now decides per glossary language code:

#.  The default language always provides the terms of its own code.
#.  Otherwise the site language marked as ``preferred`` in the new site
    language setting :confval:`site-language-deeplGlossaryTerms` is used,
    the lowest language id when several are marked.
#.  Otherwise the site language with the lowest language id having terms is
    used.

Cases the site configuration does not decide unambiguously are reported as
warnings by the backend synchronization and the ``deepl:glossary:sync``
command, and are logged.

Impact
======

Glossaries of sites with several site languages sharing a glossary language
code may contain different terms after the next synchronization.

Affected installations
======================

Installations with several site languages sharing a language code, for
example German and German (Austria), English (UK) and English (US), or
Portuguese (Portugal) and Portuguese (Brazil).

Migration
=========

Mark the site language that should provide the glossary terms as
``preferred`` in :guilabel:`Site Management > Sites`, see
:ref:`site-configuration`, and synchronize the glossaries again.
