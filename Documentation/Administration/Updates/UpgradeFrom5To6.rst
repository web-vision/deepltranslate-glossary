.. _upgrade5to6:

==================
Upgrade 5.x to 6.x
==================

Despite supported TYPO3 version functionality has been stayed the same
and no greater actions are needed.

composer-mode
=============

..  code-block:: bash
    composer require -W \
       "web-vision/deepltranslate-core":"6.0.*@dev" \
       "web-vision/deepltranslate-glossary":"6.0.*@dev"

classic-mode
=============

#.  **Get it from the Extension Manager**:
    Switch to the module :guilabel:`System > Extensions`.
    Switch to :guilabel:`Get Extensions` and search for the extension key
    *deepltranslate_core* and import the extension from the repository.

#.  **Get it from typo3.org**:
    You can always get current version from `TER`_ by downloading the zip
    version. Upload the file afterwards in the Extension Manager.

#.  **Get it from GitHub release**:
    TER upload archives are added to the corresponding GitHub release page,
    in case you need to download or update the extension and `GITHUB_RELEASES`_
    is down or not reachable.


..  _TER: https://extensions.typo3.org/extension/deepltranslate_glossary
..  _GITHUB_RELEASES: https://github.com/web-vision/deepltranslate-glossary/releases/
