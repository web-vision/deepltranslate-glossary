.. _upgrade4to5:

==================
Upgrade 4.x to 5.x
==================

There is no version `4.x`. Before `5.x` the glossary functionality was part
of `EXT:wv_deepltranslate <https://extensions.typo3.org/extension/wv_deepltranslate>`_.

The main extension has been renamed to `EXT:deepltranslate_core <https://extensions.typo3.org/extension/deepltranslate_glossary>`_
with `5.x` and `glossary` implementation extracted into this dedicated
`EXT:deepltranslate_glossary<https://extensions.typo3.org/extension/deepltranslate_glossary>`_
extension.

composer-mode
~~~~~~~~~~~~~

..  code-block:: bash

    composer remove "web-vision/wv_deepltranslate"
    composer require \
        "web-vision/deepltranslate-core":"^5" \
        "web-vision/deepltranslate-glossary":"^5"

classic-mode
~~~~~~~~~~~~

#.  **Uninstall "wv_deepltranslate" using the Extension Manager**.
    Switch to the module :guilabel:`Admin Tools > Extensions` and filter for
    :guilabel:`wv_deepltranslate` and remove (uninstall) the extension.

#.  **Ensure to remove the folder completely**.
    Run

    ..  code-block:: bash

        rm -rf typo3conf/ext/wv_deepltranslate

#.  **Get it from the Extension Manager**:
    Switch to the module :guilabel:`Admin Tools > Extensions`.
    Switch to :guilabel:`Get Extensions` and search for the extension key
    *deepltranslate_core* and import the extension from the repository.

#.  **Get it from typo3.org**:
    You can always get current version from `TER`_ by downloading the zip
    version. Upload the file afterwards in the Extension Manager.

..  _TER: https://extensions.typo3.org/extension/deepltranslate_glossary
..  _GITHUB_RELEASES: https://github.com/web-vision/deepltranslate-glossary/releases/
