# Contributing to deepltranslate-glossary

Thank you for helping. This file explains how a change gets into this
extension: which branch, how to test it, how to write the commit and what a
pull request needs to be merged.

## Table of contents

- [Issues and security](#issues-and-security)
- [Branches](#branches)
- [Getting started](#getting-started)
- [Tests and checks](#tests-and-checks)
- [Code rules](#code-rules)
- [Documentation and changelog](#documentation-and-changelog)
- [Commit messages](#commit-messages)
- [Pull requests](#pull-requests)
- [Other DeepL extensions](#other-deepl-extensions)

## Issues and security

- Bugs and feature requests are GitHub issues, with the templates offered
  when you open one. Include the TYPO3 version, the versions of this
  extension and of deepltranslate-core, and the steps to reproduce.
- **Security issues are never reported publicly.** See
  [SECURITY.md](SECURITY.md).
- The maintainers track their work in an internal tracker, project `DPL`.
  That is why commits and pull requests refer to `DPL-123`. You do not need
  access to it.

## Branches

| Branch | Version | TYPO3        | PHP        | deepltranslate-core | State                         |
|--------|---------|--------------|------------|---------------------|-------------------------------|
| `main` | 6.x     | 13.4, 14.3   | 8.2 to 8.5 | 6.1.x               | development of the next 6.x   |
| `5`    | 5.x     | 12.4, 13.4   | 8.1 to 8.5 | 5.1.x               | bug fixes and security fixes  |

- Open a pull request against `main`. A fix that is needed in 5.x as well is
  a second pull request against `5`, made after the first one, from a branch
  with the suffix `-5` (`bugfix/21-shared-iso-code` and
  `bugfix/21-shared-iso-code-5`). Its title ends with `(5.x)`. The
  maintainers can do that second one for you.
- The branch `5` has its own `CONTRIBUTING.md`. Read that one for a change
  there: the supported versions and some code rules differ.
- Pull requests may be stacked: a pull request based on the branch of
  another one, for example on the feature branch `glossary-api-v3`. It is
  rebased onto its new base once the one below it is merged.

## Getting started

You need git, bash and docker or podman. Everything else runs in containers
through `Build/Scripts/runTests.sh`, the same script the CI uses.

```bash
git clone git@github.com:web-vision/deepltranslate-glossary.git
cd deepltranslate-glossary
Build/Scripts/runTests.sh -h                        # all options and suites
Build/Scripts/runTests.sh -t 13 -s composerUpdate   # install for TYPO3 v13
Build/Scripts/runTests.sh -t 13 -s unit
```

- `-t` selects the TYPO3 version (`13`, default, or `14`). The installation
  in `.Build/` exists once: run `-s composerUpdate` with the same `-t` before
  the suites of that version, and never two versions at the same time.
- `-b docker` or `-b podman` selects the container binary. Without it,
  podman is used when it is installed.
- `-p` selects the PHP version (default 8.2).
- deepltranslate-core is installed from Packagist, in the version
  `composer.json` requires. Unreleased changes of core are not part of your
  tests until they are merged there.

## Tests and checks

A pull request is merged when these are green for TYPO3 v13 and v14. Run them
locally before you push:

| Check                         | Command                                              |
|-------------------------------|------------------------------------------------------|
| composer.json valid           | `Build/Scripts/runTests.sh -t 13 -s composer -- validate --strict --no-check-lock --no-check-version` |
| Coding style (check only)     | `Build/Scripts/runTests.sh -t 13 -s cgl -n`          |
| Coding style (fix)            | `Build/Scripts/runTests.sh -t 13 -s cgl`             |
| PHPStan                       | `Build/Scripts/runTests.sh -t 13 -s phpstan`         |
| PHP lint                      | `Build/Scripts/runTests.sh -t 13 -s lintPhp`         |
| Unit tests                    | `Build/Scripts/runTests.sh -t 13 -s unit`            |
| Functional tests              | `Build/Scripts/runTests.sh -t 13 -s functional`      |
| Functional tests, other DBMS  | `... -s functional -d mariadb` (also `mysql`, `postgres`) |
| Exception codes unique        | `Build/Scripts/runTests.sh -s checkExceptionCodes`   |
| Test method names             | `Build/Scripts/runTests.sh -s checkTestMethodsPrefix`|
| UTF-8 without BOM             | `Build/Scripts/runTests.sh -s checkBom`              |
| Changelog entries             | `Build/Scripts/runTests.sh -s checkRst`              |
| Documentation renders         | `Build/Scripts/runTests.sh -s renderDocumentation`   |

The same with `-t 14` after `-s composerUpdate -t 14`.

- The functional tests talk to a DeepL mock server container, not to DeepL.
  They need no API key and cost nothing.
- A bug fix comes with a test that fails without it. A new feature comes
  with tests.
- `-s downloadGerritPatch` downloads a TYPO3 Core change from Gerrit as a
  composer patch into `patches/`, for testing against an unreleased fix of
  TYPO3. Such a patch is not committed.

## Code rules

- `declare(strict_types=1);` in every PHP file, classes `final` unless they
  are meant to be extended, dependencies as `readonly` promoted constructor
  properties (or `final readonly` classes).
- Services are stateless. They carry no data from one call to the next.
- Dependency injection through Symfony attributes (`#[AsEventListener]`,
  `#[AsCommand]`, `#[Autoconfigure]`, ...). `Services.yaml` keeps the
  defaults, the resource and the cache service.
- **No new TYPO3 version checks.** This extension has no `Core13/` and
  `Core14/` directories. The few differences between v13 and v14 are handled
  where they occur (`Classes/EventListener/GlossarySyncButtonProvider.php`,
  the TCA overrides of the glossary tables). A difference that needs more
  than that gets separate classes per version, the way deepltranslate-core
  does it, in a change of its own.
- Every exception gets a unique code, the Unix timestamp of the moment you
  write it.
- Test methods use the `#[Test]` attribute and do not start with `test`.
- Coding style is PSR-2 with the PHP 7.4 migration rules
  (`Build/php-cs-fixer/php-cs-rules.php`). PHPStan runs on level 8 with a
  baseline per TYPO3 version in `Build/phpstan/`. A change does not add to
  the baselines.

## Documentation and changelog

- The documentation is reStructuredText in `Documentation/`, rendered with
  `-s renderDocumentation`. The rendered result is not committed.
- A feature, a breaking change, a deprecation or a fix an editor or
  integrator notices gets a changelog entry in
  `Documentation/Changelog/<major.minor>/`, named like the TYPO3 Core
  changelog: `Feature-<Topic>.rst`, `Breaking-...`, `Deprecation-...`,
  `Important-...`. It is part of the same commit. Create the folder of the
  minor version when it does not exist yet.
- An entry has the format of the TYPO3 Core changelog, without an issue
  number. `-s checkRst` checks it, as the CI does:

  ```rst
  .. include:: /Includes.rst.txt

  ..  _feature-glossarynameevent-1791124339:

  ==========================================
  Feature: Event to modify the glossary name
  ==========================================

  Description
  ===========

  Impact
  ======

  Affected installations
  ======================

  Migration
  =========

  .. index:: PHP-API, ext:deepltranslate_glossary
  ```

  - The first line includes `/Includes.rst.txt`.
  - A link target unique in the whole manual stands right before the title:
    the type and the topic in lower case, and the current Unix timestamp.
  - The title starts with the type of the file name, `Feature: `,
    `Breaking: `, `Deprecation: ` or `Important: `.
  - The last line is an `.. index::` line with at least one keyword
    (`Backend`, `CLI`, `Database`, `Frontend`, `PHP-API`, `TCA`, `YAML` and
    the others listed in `Build/Scripts/validateRstFiles.php`) and
    `ext:deepltranslate_glossary`. A `Breaking-` or `Deprecation-` entry
    ends it with `NotScanned`, as the TYPO3 extension scanner does not know
    this extension.
  - Never change the link target of an entry once it is on `main`. The
    rendered manual is published from there, and links point at it.
- Only the changelog entries are checked by `-s checkRst`. The other pages
  of the manual are checked by rendering it with `-s renderDocumentation`,
  which has to finish without a warning caused by the change.

## Commit messages

The [TYPO3 Core commit message rules](https://docs.typo3.org/m/typo3/guide-contributionworkflow/main/en-us/Appendix/CommitMessage.html):

```
[BUGFIX] DPL-233: Decide which site language feeds a glossary code (#21)

Explain why the change is needed and what it does, not how the diff
looks. Wrap the body at 72 characters.
```

- Subject: a tag (`[FEATURE]`, `[BUGFIX]`, `[TASK]`, `[DOCS]`, `[SECURITY]`,
  `[!!!]` in front for a breaking change), the internal issue if there is one,
  imperative mood, at most 52 characters where possible.
- A GitHub issue goes at the end of the subject (`(#21)`) or into the footer
  (`Resolves: #21`).

## Pull requests

- **One pull request carries one commit.** The title of the pull request is
  the subject of the commit. Review changes are amended into the commit and
  force pushed, not added as new commits.
- Rebase onto the current `main` instead of merging it in. The branch is
  merged with "rebase and merge", the only method allowed.
- Required: one approving review and the checks `code quality with core v13
  (8.2)`, `all tests with core v13 (8.2)`, `all tests with core v13 (8.5)`,
  the same for v14, and `render documentation`.
- Name the branch `<type>/<topic>`, for example `bugfix/21-shared-iso-code`
  or `feature/67-term-length-1024`.

## Other DeepL extensions

This extension builds on `web-vision/deepltranslate-core`, which it requires
with a range on its minor version (`~6.1.0`). It hands the glossary to every
translation of core through core's event `DeepLGlossaryIdEvent`, and its
DeepL clients extend core classes marked `@internal`. A change in core can
need a matching change here, and every new minor version of core needs a
raised requirement here.

`deepltranslate-auto-renew` and `deepltranslate-mass` know this extension by
name: auto-renew skips its tables (`tx_deepltranslate_glossary`,
`tx_deepltranslate_glossaryentry`) and its glossary folders (doktype 254,
module `glossary`), mass skips the glossary folders. Renaming any of these needs changes there. The
maintainers coordinate that: say in your pull request when you know a change
affects them.
