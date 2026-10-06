..  _knownIssues:

Known Issues
============

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
..  _issue #107: https://github.com/web-vision/deepltranslate-glossary/issues/107
