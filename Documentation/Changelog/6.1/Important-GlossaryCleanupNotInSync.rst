.. include:: /Includes.rst.txt

..  _important-glossarycleanupnotinsync-1791313084:

=====================================================================
Important: Glossary cleanup detaches only glossaries unknown to DeepL
=====================================================================

Description
===========

The option ``--notinsync`` of the command ``deepl:glossary:cleanup`` failed
as soon as a single glossary record carried a DeepL glossary id. Fixing the
query alone would have detached every synchronized glossary folder from its
glossary at DeepL.

The option now compares the glossary records with the glossaries DeepL lists
for the configured API key. Only a record whose DeepL glossary no longer
exists loses its glossary id and its dictionaries. Nothing is deleted at
DeepL.

When DeepL lists no glossary at all, the command detaches nothing and shows a
warning. Check the configured API key in that case. When the list cannot be
fetched, the command fails without detaching anything.

Impact
======

``vendor/bin/typo3 deepl:glossary:cleanup --notinsync`` runs again and keeps
the glossary ids of all glossaries DeepL still knows.

Affected installations
======================

Installations using ``deepl:glossary:cleanup --notinsync``, manually or as a
scheduler task.

Migration
=========

None. A detached glossary folder gets a new DeepL glossary with its next
synchronization, see :ref:`sync-cli`.

.. index:: CLI, ext:deepltranslate_glossary
