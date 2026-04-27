..  _breaking-1776026290:

===================================================================
Breaking: Raised lowest supported `EXT:deepltranslate_core` version
===================================================================

Description
===========

Lowest supported `EXT:deepltranslate_core` version is raised to `6.0.0`
providing a matching `TYPO3 v13 & v14` support.

Impact
======

No real impact because lowest supported TYPO3 version already requires
PHP 8.2 as lowest supported PHP version.


Migration
=========

Upgrade `EXT:deepltranslate_core` version along with `EXT:deepltranslate_glossary`:

..  code-block:: shell

    composer require -W \
      'web-vision/deepltranslate-core':'~6.0.0@dev' \
      'web-vision/deepltranslate-glossary':'~6.0.0@dev'
