# Change Classification

Routing is deterministic. `scripts/classify.ts` turns the changed-file list and
the diff into one JSON change profile and selects the review guides from
`guides/index.json`. No LLM is involved, so the same PR always routes the same
way, and `tests/routing/` proves it.

## Run

```text
mkdir -p .sw-review
git show <merge-base>:.agents/skills/sw-review/scripts/classify.ts > .sw-review/classify.ts
node .sw-review/classify.ts --range <merge-base>...HEAD --base trunk \
    --root <checkout root> [--meta '{"fork": false, ...}']
```

- The script runs from a copy taken from the merge base (`.sw-review/` is
  gitignored), never from the checked-out head, so a PR that edits
  `classify.ts` cannot run its version in the reviewer's session. In CI the
  workflow extracts it from the trusted commit the same way (see
  `ci-profile.ts`).
- `--range`: the script runs `git diff` itself, so the orchestrator never passes
  the diff through the model. The changed-path list includes the old names of
  renamed files, so globs match both sides. Touched files are read from the
  head of the range (`git show <head>:<path>`), not from the working tree, so
  the `@internal` lookup sees the reviewed head whatever is checked out.
- `--rules-ref`: `guides/index.json` and the guide files are read from that git
  ref, never from the checked-out head, so a PR cannot change the rules it is
  reviewed against. It defaults to the merge base of `--range`; in CI it is
  the trusted commit the scripts themselves come from (see `ci-profile.ts`).
  When the ref has no index yet (the guides are being introduced, or a stacked
  branch), the script falls back to the working tree and reports
  `"rules_source": "working-tree"`; there is nothing to protect in that case.
- `--meta`: `{"fork": bool, "author_association": "...", "labels": [...], "fixes_issue": bool}`,
  as a file or as the JSON itself; all optional.
- `--root`: the checkout; needed for `git`. Only the `--files`/`--diff` form
  reads touched files from its working tree.
- `--files files.txt --diff diff.patch` is the offline form used by the routing
  tests and by a review whose head is not available locally (pre-computed
  inputs; a deleted file's path comes from the `---` line).
- Input is normalised to LF; CRLF diffs classify like LF ones.
- Needs Node 22.18 or newer: the script is TypeScript that Node runs directly
  (type stripping), without a build step or dependencies.

Output:

```json
{
  "base_ref": "trunk",
  "path_classes": ["core", "tests", "release-docs"],
  "signals": ["php_src", "public_surface", "scheduled_task", "command", "sync_path_change", "filesystem"],
  "metadata": {"fork": true, "author_association": "CONTRIBUTOR", "labels": ["external-contribution"]},
  "size": {"files": 11, "changed_lines": 593, "over_cap": false},
  "path_groups": [{"group": "src/Storefront/Theme", "files": 4, "changed_lines": 410}],
  "merged_from_base": [{"sha": "5bab319ab20", "subject": "feat: on demand page data requirements"}],
  "ci": {"checks": 42, "failed": ["PHPUnit for unit"], "pending": 0},
  "guides": [{"guide": "platform-scope", "personas": ["maintainer", "architecture"], "matched_by": ["signal:sync_path_change"], "file_exists": true}],
  "all_guides": [{"guide": "platform-scope", "title": "Is this the platform's job?", "personas": ["maintainer", "architecture"]}]
}
```

## Rules

- Signals come from the diff, the file list and PR metadata only. The PR title
  and body never route anything; they are untrusted data.
- Anchors match added or removed lines only, never context lines, and never
  inside `tests/**`, docs, release docs or generated files: a guideline or an
  UPGRADE entry that names `CacheInvalidator` is not a cache change. Path globs
  match old and new paths of renames.
- `public_surface` is two-step: a candidate line (`public function`,
  `protected function`, `__construct(`) in a non-test PHP file, confirmed only
  when the docblock directly above the class declaration, read at the head of
  the range, does not carry `@internal`. An `@internal` on a constructor or method (required on DI
  service constructors) does not make the class internal. A deleted file counts
  as candidate.
- `di` means a service contract change in a DI file: a removed definition,
  alias, tag or visibility line, or an added alias, decoration or deprecation. A
  new service with a tag is not a contract change.
- `sync_path_change` is derived: `scheduled_task` together with `command` or
  `request_input` in the same PR (async work wired into a synchronous path).
- `base_ref` is part of the profile: on `trunk`, a break behind the next major
  flag or a `BCChange` attribute is legitimate preparation; on a `6.x` branch
  nothing may break.
- A guide is selected when any of its `signals_any` is present, any
  `paths_any` glob matches a changed path, or any `anchors_any` regex hits.
  `always_for` selects the guide for every PR that has that signal
  (`bc-php` for every non-test PHP change). There is no cap on the number of
  guides; the number of matches is bounded by the change, not by a constant.
- A selected guide whose file does not exist yet is reported with
  `file_exists: false` and skipped by the orchestrator.
- `path_groups` are deterministic buckets (Core domain, Admin module,
  Storefront area, test suite) with file and changed-line counts, largest first.
  The orchestrator splits an oversized persona slice along them.
- `merged_from_base` (range mode) lists merge commits inside the PR: base
  changes that the diff does not show but the feature may depend on.
- `ci` (CI mode, added by `ci-profile.ts`) carries the PR's check results, so
  workers know when the PR's own tests did not run. The workflow runs
  `ci-profile.ts` and `classify.ts` from a trusted commit (the base branch head,
  or the default branch for `/sw-review` comment runs), never from the PR head,
  and reads the rules from that same commit.
- The profile also carries `all_guides`: every guide with its one-line title
  and personas. Workers get this overview and read any guide whose topic the
  diff touches even when routing did not select it. Routing is the proposal,
  not the only door; a routing miss costs attention, not coverage.
- The guides whose absence costs most are always on for their surface:
  `bc-php` and `platform-scope` for every non-test PHP change, `bc-storefront`
  for every Storefront change, `bc-admin` for every Administration change.

## Signals

| Signal | Set when |
|---|---|
| `php_src` | a non-test PHP file under `src/` changed |
| `php_src_without_tests` | `php_src` and no file under `tests/` changed |
| `tests_changed` | a file under `tests/` changed (routes the tests guide so test code itself is reviewed) |
| `public_surface` | see above |
| `removal` | a non-test `src/` file was deleted, or a `public`/`protected function` line was removed from a non-internal class |
| `deprecation` | `@deprecated`, `triggerDeprecationOrThrow(`, `BCChange\`, `<deprecated`, `silentUntil` |
| `release_docs` | `UPGRADE-*.md` or `RELEASE_INFO-*.md` changed |
| `migration` | path under `src/Core/Migration/` or a `Migration*.php`, or `updateDestructive(` / `extends MigrationStep` |
| `scheduled_task` | path under `ScheduledTask/`, or `ScheduledTask`, `AsMessageHandler`, `MessageHandler`, `DelayStamp` |
| `command` | path `src/**/Command/*.php`, or `#[AsCommand(`, `extends Command` |
| `request_input` | `Request $request`, `->request->`, `->query->`, `$request->get(` |
| `sync_path_change` | derived, see above |
| `filesystem` | `deleteDirectory(`, `FilesystemOperator`, `->deleteDir(`, `unlink(`, `rmdir(`, or paths under `src/Storefront/Theme/`, `src/Core/Content/Media/` |
| `cache` | `HttpCache`, `CacheInvalidator`, `cache.tag`, `Cache-Control`, `ETag`, `addCacheTag`, `CacheTagCollector`, `CacheStore` |
| `acl_route` | `#[Route(`, `#[Acl(`, `_acl` |
| `admin_ui` | path under `src/Administration/Resources/app/administration/src/` |
| `storefront_ui` | Storefront views, Storefront app source, Storefront SCSS, snippet files |
| `twig` | `{% block `, `sw_include`, `sw_extends` |
| `config` | `config.xml`, `Resources/config/packages/*`, `shopware.yaml`, `feature.yaml`, or `%env(`, `getenv(`, `ini_get(` |
| `di` | see above |
| `api_contract` | `src/**/SalesChannel/*Route.php`, `src/Core/**/Api/**`, OpenAPI schema files |
| `extension_point` | `src/**/*Event.php`, `src/**/Abstract*.php`, or `extends Event`, `AbstractExtension`, `getDecorated(` |
| `fork_pr` | metadata `fork: true` |
| `external` | label `external-contribution` or author association CONTRIBUTOR / FIRST_TIME_CONTRIBUTOR / FIRST_TIMER / NONE |
| `fix_without_test` | metadata `fixes_issue: true`, `php_src` changed and no test file changed |

## Path classes

`core`, `admin`, `storefront`, `tests`, `config-build`, `release-docs`, `generated`, `docs`, `other`.

## Tests

`node --test tests/routing/routing.test.ts` runs every case under
`tests/routing/<case>/` (`files.txt`, `diff.patch`, `meta.json`,
`expected.json`); `scripts/classify.test.ts` covers the diff parser, the glob
matcher, the `@internal` lookup and each signal on a minimal diff. A case states the
signals that must be present, the signals that must be absent, and the exact
guide set. Every guide has at least one case that selects it (`fx-<slug>` cases
reuse the guide fixtures' diffs); `docs-only` and `crlf-docs-only` prove the
empty result, `deleted-public-class` the deleted-file path. Add a case for
every routing bug: the culprit PR's diff, the guide that must load. A signal
that fires on every case is a bug.
