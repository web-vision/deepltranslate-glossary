[![Latest Stable Version](https://poser.pugx.org/web-vision/deepltranslate-glossary/v/stable.svg?style=for-the-badge)](https://packagist.org/packages/web-vision/deepltranslate-glossary)
[![License](https://poser.pugx.org/web-vision/deepltranslate-glossary/license?style=for-the-badge)](https://packagist.org/packages/web-vision/deepltranslate-glossary)
[![TYPO3 12.4](https://img.shields.io/badge/TYPO3-12.4-green.svg?style=for-the-badge)](https://get.typo3.org/version/12)
[![TYPO3 12.4](https://img.shields.io/badge/TYPO3-13.4-green.svg?style=for-the-badge)](https://get.typo3.org/version/13)
[![Total Downloads](https://poser.pugx.org/web-vision/deepltranslate-glossary/downloads.svg?style=for-the-badge)](https://packagist.org/packages/web-vision/deepltranslate-glossary)
[![Monthly Downloads](https://poser.pugx.org/web-vision/deepltranslate-glossary/d/monthly?style=for-the-badge)](https://packagist.org/packages/web-vision/deepltranslate-glossary)

# TYPO3 extension `deepltranslate_glossary`

|                  | URL                                                                     |
|------------------|-------------------------------------------------------------------------|
| **Repository:**  | https://github.com/web-vision/deepltranslate-glossary                   |
| **Read online:** | https://docs.typo3.org/p/web-vision/deepltranslate-glossary/main/en-us/ |
| **TER:**         | https://extensions.typo3.org/extension/deepltranslate_glossary/         |
| **ISSUES:**      | https://github.com/web-vision/deepltranslate-glossary/issues/           |
| **RELEASES:**    | https://github.com/web-vision/deepltranslate-glossary/releases/         |

## Description

This extension provides glossary-flavoured translations for the TYPO3 extension
[deepltranslate_core](https://github.com/web-vision/deepltranslate-core).

## Compatibility

## Compatibility

| Branch | State                       | Composer Package Name              | TYPO3 Extension Key     | Version       | TYPO3     | PHP                                          |
|--------|-----------------------------|------------------------------------|-------------------------|---------------|-----------|----------------------------------------------|
| main   | development, active support | web-vision/deepltranslate-glossary | deepltranslate_glossary | ^6, 6.0.x-dev | v12 + v13 | 8.2, 8.3, 8.4, 8.5 (depending on TYPO3)      |
| 5      | active support              | web-vision/deepltranslate-glossary | deepltranslate_glossary | ^5, 5.1.x-dev | v12 + v13 | 8.1, 8.2, 8.3, 8.4, 8.5 (depending on TYPO3) |

## Features

* TYPO3-conform database records for own glossaries
* Synchronize button in glossary module folders
* Managing for glossaries by CLI
* Cleanups and auto-updates by CLI scripts or scheduler tasks

## Installation

Install with your favour:

* [Composer](https://packagist.org/packages/web-vision/deepltranslate-glossary)
* [TER / Extension Manager](https://extensions.typo3.org/extension/deepltranslate_glossary/)
* [Git](https://github.com/web-vision/deepltranslate-glossary)

We prefer composer installation:

```bash
composer require \
  'web-vision/deepltranslate-glossary':'~5.1.1'
```

## Sponsors

We very much appreciate the sponsorship of the developments and features in the
DeepL Translate Extension for TYPO3.

### DeepL Glossary feature sponsored by

* [Universität Osnabrück](https://www.uni-osnabrueck.de)
* [Hochschule für Musik Würzburg](https://www.hfm-wuerzburg.de)
* [Carl von Ossietzky Universität Oldenburg](https://uol.de/)
* [Friedrich-Ebert-Stiftung](https://www.fes.de)

## Create a release (maintainers only)

Prerequisites:

* git binary
* ssh key allowed to push new branches to the repository
* GitHub command line tool `gh` installed and configured with user having permission to create pull requests.

**Create release**

> Set `RELEASE_BRANCH` to branch release should happen, for example: 'main'.
> Set `RELEASE_VERSION` to release version working on, for example: '5.0.0'.

> [!IMPORTANT]
> Requires `GitHub cli tool` with personal token and
> maintainer permission on the extension repository.

```shell
echo '>> Create release' ; \
  RELEASE_BRANCH='main' ; \
  RELEASE_VERSION='5.1.2' ; \
  NEXT_DEV_VERSION='5.1.3' ; \
  git checkout main && \
  git fetch --all && \
  git pull --rebase && \
  git checkout ${RELEASE_BRANCH} && \
  git pull --rebase && \
  git checkout -b release-${RELEASE_VERSION} && \
  sed -i "s/^COMPOSER_ROOT_VERSION.*/COMPOSER_ROOT_VERSION=\"${RELEASE_VERSION}\"/" Build/Scripts/runTests.sh && \
  tailor set-version ${RELEASE_VERSION} && \
  echo "${RELEASE_VERSION}
" > VERSION && \
  git add . && \
  git commit -m "[RELEASE] ${RELEASE_VERSION}" && \
  git push --set-upstream origin release-${RELEASE_VERSION} && \
  gh pr create --fill --base ${RELEASE_BRANCH} --title "[RELEASE] ${RELEASE_VERSION}" && \
  gh pr view --web && \
  sleep 30 && \
  gh pr checks --watch --interval 2 && \
  sleep 5 && \
  gh pr merge -rd --admin && \
  git remote prune origin && \
  git tag ${RELEASE_VERSION} && \
  git push --tags && \
  git checkout -b set-dev-version-${NEXT_DEV_VERSION} && \
  tailor set-version ${NEXT_DEV_VERSION} && \
  echo "${NEXT_DEV_VERSION}-dev" > VERSION && \
  sed -i "s/^COMPOSER_ROOT_VERSION.*/COMPOSER_ROOT_VERSION=\"${NEXT_DEV_VERSION}-dev\"/" Build/Scripts/runTests.sh && \
  sed -i "s/^  RELEASE_VERSION=.*/  RELEASE_VERSION='${RELEASE_VERSION}' ; \\\/" README.md && \
  sed -i "s/^  NEXT_DEV_VERSION=.*/  NEXT_DEV_VERSION='${NEXT_DEV_VERSION}' ; \\\/" README.md && \
  git add . && \
  git commit -m "[TASK] Set \"${NEXT_DEV_VERSION}-dev\"" && \
  gh pr create --fill --base ${RELEASE_BRANCH} --title "[TASK] Set \"${NEXT_DEV_VERSION}-dev\"" && \
  gh pr view --web && \
  sleep 30 && \
  gh pr checks --watch --interval 2 && \
  sleep 5 && \
  gh pr merge -rd --admin && \
  git remote prune origin
```

This triggers the `on push tags` workflow (`publish.yml`) which creates the upload package,
creates the GitHub release and also uploads the release to the TYPO3 Extension Repository.
