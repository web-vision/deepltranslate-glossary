.. include:: /Includes.rst.txt

..  _important-glossarysyncfailuresarereported-1791322312:

=========================================================
Important: Failing glossary synchronizations are reported
=========================================================

Description
===========

A failing request to DeepL fails the synchronization of a glossary folder
with the error DeepL answered. Before, the failure was only logged, and the
synchronization could store an empty glossary id together with a fresh
synchronization time, so the folder looked synchronized while no glossary
existed at DeepL.

The backend synchronization button shows the failure as message.

A failing folder no longer stops the command
:bash:`vendor/bin/typo3 deepl:glossary:sync`. The remaining folders are
synchronized, and every failure is reported at the end, together with the
page id of its folder, and the command ends with a failing exit code. Only a
refused API key or an exceeded quota stops the command at once, as every
remaining folder would fail the same way. The command also reports a folder
whose glossary was removed because it holds no terms any more, and tells when
no glossary folder exists at all.

A term translated into a language which has been removed from the site
configuration is skipped instead of failing the folder.

Impact
======

A scheduler task or deployment running the synchronization command fails when
a single folder fails, while the other folders are synchronized.

Affected installations
======================

Installations synchronizing glossary folders, in particular through the
command line or a scheduler task.

Migration
=========

Check the system log and the output of the command after a failure, for
example for an invalid API key or a reached glossary limit of the DeepL
account, and synchronize the folder again.

.. index:: Backend, CLI, ext:deepltranslate_glossary
