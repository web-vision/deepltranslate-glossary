..  _knownIssues:

Known Issues
============

Copying a glossary folder
-------------------------

A copied glossary folder shares the DeepL glossary of the folder it was copied from,
until the copy is synchronised. Synchronising the copy then changes the glossary of
the original folder. Create a new glossary folder instead of copying a synchronised
one, see `issue #106`_.

Terms without a term in the default language
--------------------------------------------

A term created directly in another language, without a term in the default language
it translates, can be paired with an unrelated term of a third language. Translate
every term from its term in the default language, see `issue #107`_.

Editing a term during a synchronisation
---------------------------------------

A term saved while its folder is being synchronised is reported as synchronised,
although DeepL still holds its former state. Synchronise the folder again after
editing terms during a running synchronisation, see `issue #105`_.

If you find another issue, feel free to :ref:`contribute <contribution>`.

..  _issue #105: https://github.com/web-vision/deepltranslate-glossary/issues/105
..  _issue #106: https://github.com/web-vision/deepltranslate-glossary/issues/106
..  _issue #107: https://github.com/web-vision/deepltranslate-glossary/issues/107
