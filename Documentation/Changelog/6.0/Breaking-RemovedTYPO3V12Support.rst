..  include:: /Includes.rst.txt

..  _breaking-1776025250:

===================================
Breaking: Removed TYPO3 v12 support
===================================

Description
===========

Support for TYPO3 v12 has been removed for `6.x` based on our dual
TYPO3 core version support per major version as casual support matrix.

This includes removing code paths and configurations only required for
TYPO3 v12.

Impact
======

TYPO3 v12 or older instances cannot update to the `6.x` version and are
required to upgrade TYPO3 to be able to install the next version of the
`EXT:deepltranslate_glossary` together with `EXT:deepltranslate_core (6.x)`
and related private addons when released in a compatible version.

Extension cannot be installed in that version but does not break otherwise.

Affected installations
======================

TYPO3 v12 or older instances with `EXT:deepltranslate_core` version `5.x`
and `EXT:deepltranslate_glossary` version `5.x`.


Migration
=========

Upgrade TYPO3 to supported version for `6.x` beforehand or in the same step
with upgrading/installing `EXT:deepltranslate_glossary`.
