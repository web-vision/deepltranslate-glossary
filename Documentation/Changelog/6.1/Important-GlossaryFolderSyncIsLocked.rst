.. include:: /Includes.rst.txt

..  _important-glossaryfoldersyncislocked-1791322313:

=====================================================================
Important: A glossary folder is synchronized by one process at a time
=====================================================================

Description
===========

A glossary folder is synchronized by one process at a time. A synchronization
started while the folder is being synchronized, for example by a scheduler
task while an editor clicks the synchronization button, does not run. The
backend reports that the folder is being synchronized already, and the
command reports the folder as skipped without failing. It can be repeated
once the running one has finished. Before, both could
create a glossary at DeepL, and one of them was left behind unused.

The lock applies per server. It does not prevent two synchronizations started
on different servers of the same installation at the same moment.

Impact
======

A synchronization started during a running one of the same folder does not
run. The backend shows a warning, the command lists the folder as skipped and
does not fail because of it.

Affected installations
======================

Installations synchronizing glossary folders through a scheduler task or the
command line while editors synchronize them in the backend.

Migration
=========

Synchronize the folder again once the running synchronization has finished.

.. index:: Backend, CLI, ext:deepltranslate_glossary
