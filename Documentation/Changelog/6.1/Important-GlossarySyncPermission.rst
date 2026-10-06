..  _important-glossarysyncpermission-1791313278:

=====================================================================
Important: Synchronizing a glossary folder checks the user permission
=====================================================================

Description
===========

The button :guilabel:`Synchronise Glossaries` was only shown to backend
users allowed to synchronize the folder, but the backend route behind the
button did not check the permission itself.

Button and route now ask the same question. A backend user may synchronize
a glossary folder when all of these apply:

#.  The user is an administrator, or a group of the user grants the
    permission :guilabel:`Allowed Glossary Sync`.
#.  The user may edit glossary terms (table
    :sql:`tx_deepltranslate_glossaryentry`).
#.  The folder is in a database mount of the user and the user has the
    page permission to edit its content.

A synchronization request without the permission is refused with an error
message, and the glossaries of the folder stay untouched.

After the synchronization, the backend returns to the page it came from
only when that is a path on the same host. Otherwise, and when the return
URL is missing, it shows the folder in the list module.

Impact
======

Backend users missing one of the requirements above can no longer
synchronize a glossary folder, also not by calling the route directly. The
button is shown to the same users as before.

Affected installations
======================

Installations with backend users who are not administrators and
synchronize glossaries.

Migration
=========

Grant :guilabel:`Allowed Glossary Sync`, the right to modify the table
:sql:`tx_deepltranslate_glossaryentry`, a database mount of the glossary
folder and the page permission to edit its content to every backend group
that synchronizes glossaries.
