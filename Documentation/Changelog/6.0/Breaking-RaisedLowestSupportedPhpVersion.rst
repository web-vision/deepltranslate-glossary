..  _breaking-1776025649:

=============================================
Breaking: Raised lowest supported php version
=============================================

Description
===========

Support for `PHP 8.1` has been removed and `PHP 8.2` required as
lowest supported `PHP` version.

That matches supported TYPO3 versions and therefore not breaking on
their own as the lowest TYPO3 version requires `PHP 8.2+` already.

Impact
======

No real impact because lowest supported TYPO3 version already requires
PHP 8.2 as lowest supported PHP version.

Migration
=========

Upgrade PHP version for the web-server and also when using composer and
php commands, for example the `bin/typo3` command line tool.
