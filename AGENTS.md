# Agent instructions

Instructions for coding agents working in this repository. `CLAUDE.md`
imports this file. Read [CONTRIBUTING.md](CONTRIBUTING.md) first: its rules
apply to you in full. This file adds what an agent needs on top of it.

## Know which line you are on

This file belongs to the branch `main`: deepltranslate-glossary 6.x, TYPO3
13.4 and 14.3, PHP 8.2 to 8.5, deepltranslate-core 6.1. **The branch you work
on decides the rules.** For work on `5` (5.x, TYPO3 12.4 and 13.4), switch to
that branch and follow its own `AGENTS.md` and `CONTRIBUTING.md`, never this
one. A backport is written for the target branch, it is not a copy of the
change on `main`.

Check before you start:

```bash
git branch --show-current
git status -sb
```

A pull request may be stacked on the branch of another one (the feature
branch `glossary-api-v3` has several). Check its base with
`gh pr view <number> --json baseRefName` before you rebase it.

## Rules

- **Never write to a remote** (push, pull request, issue, comment, review,
  merge) unless the maintainer asks for exactly that.
- **Never credit a tool or a model** in commits, pull requests, issues, code
  comments or documentation. No `Co-authored-by` for it, no "Generated
  with" line. The human who submits the change is its author.
- Scratch files, plans, reports and downloads go into `.agent/` (git
  ignored), never into the tracked tree and never into `/tmp`.
- Verify by running, not by recalling: the suites below, `git`, `composer`.
  Say what you ran and what you did not run.
- An issue reference (`DPL-123`, `#21`) is written only when it is known to
  exist. Do not invent one.

## Running the test harness as an agent

- **Set `CI=true`.** Without a terminal, `runTests.sh` fails with "the input
  device is not a TTY" unless `CI` is `true`:

  ```bash
  export CI=true
  Build/Scripts/runTests.sh -b docker -t 13 -s composerUpdate
  Build/Scripts/runTests.sh -b docker -t 13 -s cgl -n
  Build/Scripts/runTests.sh -b docker -t 13 -s phpstan
  Build/Scripts/runTests.sh -b docker -t 13 -s unit
  Build/Scripts/runTests.sh -b docker -t 13 -s functional
  ```

- **One TYPO3 version at a time.** `.Build/` holds the installation of the
  last `composerUpdate`. Run all suites of v13, then `composerUpdate -t 14`
  and the suites of v14. Never run two `runTests.sh` calls of this checkout
  in parallel.
- `composerUpdate` rewrites `composer.json` while it runs and restores it
  afterwards (`composer.json.orig`). Do not interrupt it, and never commit a
  `composer.json` changed by it.
- `-s cgl` changes files, `-s cgl -n` only checks. Run the check before you
  commit, the fix only on purpose.
- Pass test filters behind `--`: `-s unit -- --filter SomeTest`.
- The functional tests use a DeepL mock server. Never call the real DeepL
  API, never print, log or write a DeepL key anywhere.
- `-s downloadGerritPatch` writes into `patches/`. Never commit those files.
- Done means: the suites of the changed area green on **both** v13 and v14,
  `cgl -n` and `phpstan` green on both, `renderDocumentation` when
  `Documentation/` changed.

## Code you are likely to touch

- The glossary reaches the translations of deepltranslate-core through the
  listener `Classes/EventListener/LocalGlossary.php` on core's
  `DeepLGlossaryIdEvent`. The DeepL clients in `Classes/Client/` extend
  core's `AbstractClient` and implement its `ClientInterface`, both marked
  `@internal` in core. A change of core's constructor or client layout breaks
  them. Say so when you see it.
- Glossary folders are pages with doktype 254 and module `glossary`, the
  terms live in `tx_deepltranslate_glossary` and
  `tx_deepltranslate_glossaryentry`. deepltranslate-auto-renew and
  deepltranslate-mass skip these by name. Do not rename them without being
  asked, and name the follow-up changes when it is asked.
- `Classes/Hooks/UpdatedGlossaryEntryTermHook.php` runs on every DataHandler
  save, also when records are imported. It calls no DeepL API. A DataHandler
  hook that does has to skip runs with `$dataHandler->isImporting` set.
- The v13/v14 differences are handled in place
  (`GlossarySyncButtonProvider`, the TCA overrides). Do not add new version
  checks, see the code rules in CONTRIBUTING.md.

## Commits and pull requests

- One commit per pull request, following the commit rules in CONTRIBUTING.md.
  Amend and force push (`--force-with-lease`) when asked to update a pull
  request, do not add fix-up commits.
- Rebase onto the base of the pull request (`origin/main`, or the branch it
  is stacked on), never merge it in.
- When a change also needs a pull request in deepltranslate-core or another
  DeepL extension, say so, and name the order in which they have to be
  merged.
