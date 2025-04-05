[![Latest Stable Version](https://poser.pugx.org/web-vision/deepltranslate-glossary/v/stable.svg?style=for-the-badge)](https://packagist.org/packages/web-vision/deepltranslate-glossary)
[![License](https://poser.pugx.org/web-vision/deepltranslate-glossary/license?style=for-the-badge)](https://packagist.org/packages/web-vision/deepltranslate-glossary)
[![TYPO3 12.4](https://img.shields.io/badge/TYPO3-12.4-green.svg?style=for-the-badge)](https://get.typo3.org/version/12)
[![TYPO3 12.4](https://img.shields.io/badge/TYPO3-13.4-green.svg?style=for-the-badge)](https://get.typo3.org/version/13)
[![Total Downloads](https://poser.pugx.org/web-vision/deepltranslate-glossary/downloads.svg?style=for-the-badge)](https://packagist.org/packages/web-vision/deepltranslate-glossary)
[![Monthly Downloads](https://poser.pugx.org/web-vision/deepltranslate-glossary/d/monthly?style=for-the-badge)](https://packagist.org/packages/web-vision/deepltranslate-glossary)

# TYPO3 extension `deepltranslate_glossary`

This extension provides glossary-flavoured translations for the TYPO3 extension
[deepltranslate_core](https://github.com/web-vision/deepltranslate-core).

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
composer require web-vision/deepltranslate-glossary
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

**Prepare release locally**

> Set `RELEASE_BRANCH` to branch release should happen, for example: 'main'.
> Set `RELEASE_VERSION` to release version working on, for example: '5.0.0'.

```shell
echo '>> Prepare release pull-request' ; \
  RELEASE_BRANCH='main' ; \
  RELEASE_VERSION='5.0.1' ; \
  git checkout main && \
  git fetch --all && \
  git pull --rebase && \
  git checkout ${RELEASE_BRANCH} && \
  git pull --rebase && \
  git checkout -b prepare-release-${RELEASE_VERSION} && \
  composer require --dev "typo3/tailor" && \
  ./.Build/bin/tailor set-version ${RELEASE_VERSION} && \
  composer remove --dev "typo3/tailor" && \
  git add . && \
  git commit -m "[TASK] Prepare release ${RELEASE_VERSION}" && \
  git push --set-upstream origin prepare-release-${RELEASE_VERSION} && \
  gh pr create --fill-verbose --base ${RELEASE_BRANCH} --title "[TASK] Prepare release for ${RELEASE_VERSION} on ${RELEASE_BRANCH}" && \
  git checkout main && \
  git branch -D prepare-release-${RELEASE_VERSION}
```

Check pull-request and the pipeline run.

**Merge approved pull-request and push version tag**

> Set `RELEASE_PR_NUMBER` with the pull-request number of the preparation pull-request.
> Set `RELEASE_BRANCH` to branch release should happen, for example: 'main' (same as in previous step).
> Set `RELEASE_VERSION` to release version working on, for example: `0.1.4` (same as in previous step).

```shell
RELEASE_BRANCH='main' ; \
RELEASE_VERSION='5.0.1' ; \
RELEASE_PR_NUMBER='123' ; \
  git checkout main && \
  git fetch --all && \
  git pull --rebase && \
  gh pr checkout ${RELEASE_PR_NUMBER} && \
  gh pr merge -rd ${RELEASE_PR_NUMBER} && \
  git tag ${RELEASE_VERSION} && \
  git push --tags
```

This triggers the `on push tags` workflow (`publish.yml`) which creates the upload package,
creates the GitHub release and also uploads the release to the TYPO3 Extension Repository.
