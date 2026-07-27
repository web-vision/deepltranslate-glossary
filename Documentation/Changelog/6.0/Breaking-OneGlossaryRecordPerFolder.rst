..  _breaking-oneglossaryrecordperfolder-1785196802:

==========================================
Breaking: One glossary record per folder
==========================================

Description
===========

The DeepL glossary API v2 could only store a single language pair per
glossary, so a glossary folder created one glossary record, and with it one
remote glossary, for every language pair it contained.

The glossary API v3 keeps a persistent glossary holding one dictionary per
language pair. The structure follows that model:

*   :sql:`tx_deepltranslate_glossary` holds exactly one record per glossary
    folder, carrying the DeepL glossary id and the synchronization state
*   the new table :sql:`tx_deepltranslate_glossarydictionary` holds one record
    per language pair with its entry count and synchronization state

The columns :sql:`source_lang` and :sql:`target_lang` therefore no longer
describe a glossary but a dictionary.

Impact
======

Custom code reading :sql:`tx_deepltranslate_glossary` to resolve a language
pair no longer finds one glossary record per pair. A glossary folder now
resolves to a single glossary id which is valid for every language pair the
glossary contains.

Reducing a folder to one remote glossary also lowers the number of glossaries
counted against the limit of the DeepL account.

Affected installations
======================

Instances with synchronized glossaries, and any custom code querying
:sql:`tx_deepltranslate_glossary` directly.

Migration
=========

An upgrade wizard collapses the existing records of a folder into one glossary
record with its dictionaries, removes the glossaries created with the API v2
from the DeepL account and clears the local glossary id, so that the next
synchronization creates the glossary through the API v3.

The columns :sql:`source_lang` and :sql:`target_lang` are kept on
:sql:`tx_deepltranslate_glossary` until the wizard has run and are removed
with the next major version.
