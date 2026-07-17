# Administration Extension Tooling

This folder ships the TypeScript and ESLint contract for Administration
extensions. It travels with the Administration package, so a git checkout, a
Composer install, and a production shop all carry identical tooling — matching
the installed Shopware version.

## What lives here

| File | Purpose |
| --- | --- |
| `tsconfig.base.json` | Strict TypeScript preset for extension code (ESNext, Bundler resolution, `noEmit`). Resolves `vue`, `@vue/*`, and `src/*` into the installed Administration. |
| `admin-types.d.ts` | The one type surface: imports the live `global.types.ts`, the generated `entity-schema-definition.d.ts`, and `html-shim.d.ts`. Injected into every extension program via `files`. |
| `eslint.mjs` | Parameterized flat-config factory `shopwareAdminExtension(options)`. All plugins resolve from the Administration's `node_modules`. |
| `legacy-twig.mjs` | Lint preset for legacy `.html.twig` component templates (Twig-Vue processor). |
| `host-modules.json` | Declares the bare modules the Administration host provides to extensions at runtime. v1: `vue` only — the Vite externals plugin replaces exactly the bare `vue` import. If a module is added there, it must be added here and to `tsconfig.base.json` `paths` in the same change. |

## How extensions use it

You normally do not reference this folder manually. From the project root:

```bash
composer admin:setup-extension-tooling   # generate configs for all installed extensions
composer admin:check-extensions          # pick extensions to type-check + lint (interactive picker)
composer admin:check-extensions -- --only=SwagPayPal,MyPlugin   # check specific ones, no prompt
composer admin:check-extensions -- --all                        # check every extension, no prompt
composer admin:check-extensions -- --only=MyPlugin --fix        # apply ESLint autofixes
composer admin:check-extensions -- --only=MyPlugin --update-baseline  # record current findings (see Baseline)
composer admin:setup-extension-tooling -- --help                # full option reference
```

⚠ Options always need the `--` separator — Composer silently swallows anything before it
(`composer admin:setup-extension-tooling --check` runs a plain setup, not a dry-run).

In an interactive terminal `admin:check-extensions` opens a numbered picker (accepts
numbers, ranges, `a` for all, `w` for writable-only). Non-interactive shells (CI, piped)
check all extensions. `--verbose` additionally prints the underlying tool output for passing
and skipped extensions; `--show-commands` prints the exact vue-tsc/ESLint invocation per
extension; `--fix` forwards to ESLint (vendor-installed extensions only when named via
`--only`).

Setup discovers every installed extension with Administration sources (from
`var/plugins.json` — refresh it with `bin/console bundle:dump` after installing or
activating a plugin), generates one tsconfig per extension under
`var/admin-extension-tooling/`, plus root `tsconfig.json` / `eslint.config.mjs`
projections so IDEs see exactly what the check command checks. The generated root files
are covered by a marker-fenced block that setup manages in the project `.gitignore`
(skipped when the entries are already covered; opt out permanently with `--no-gitignore`).

- **Zero-config**: a plugin with no config files at all is fully covered.
- **Committed configs**: bridge the plugin with one command:

  ```bash
  composer admin:setup-extension-tooling -- --shim=<TechnicalName>
  ```

  This writes a git-ignored `.shopware-admin/` bridge (which holds the machine-specific paths and
  composes the preset) and — if the plugin has no config yet — two small **committable** files at
  the plugin's administration folder that just extend it:

  ```jsonc
  // tsconfig.json
  { "extends": "./.shopware-admin/tsconfig.json", "include": ["src/**/*.ts", "src/**/*.vue"] }
  ```
  ```js
  // eslint.config.mjs
  import shopware from './.shopware-admin/eslint.mjs';
  export default [ ...shopware, /* your own rules */ ];
  ```

  Commit those two files and edit them freely — add your own options/rules — as long as the
  `extends`/import stays. The tool never overwrites them; if you already have configs, the check
  prints a `why:` naming what is missing and a `fix:` with the exact edit. The check uses your
  committed configs, so what runs is what you see.

## Own path aliases (`tsconfig.aliases.json`)

TypeScript replaces `paths` **wholesale** across `extends`: declaring
`"paths": { "MyPlugin/*": ["src/*"] }` in your own tsconfig would erase the preset's
`vue` / `src/*` mappings. Declare aliases in a committed `tsconfig.aliases.json` next to
your config instead:

```jsonc
{ "MyPlugin/*": ["src/*"] }
```

Re-run `composer admin:setup-extension-tooling -- --shim=<TechnicalName>` afterwards: the
generated `.shopware-admin/` bridge becomes the single `paths` declarer and merges your
aliases with the preset's host paths (targets resolve relative to the plugin's
administration folder). The same mechanism covers type-only imports of host packages,
e.g. `{ "axios": ["../../../../../../../src/Administration/Resources/app/administration/node_modules/axios"] }`.

## Test files

Spec files (`**/*.spec.{ts,tsx,js}`) are type-checked by a **dedicated program** with jest
types, separate from the runtime program. The runtime tsconfig still excludes specs — its
preset sets `types: []` so the runtime globals stay runtime-only — and the check runs a
second `vue-tsc` pass over a generated spec tsconfig (`…-specs.json`) that injects
`spec-types.d.ts` (jest `describe`/`it`/`expect`, …) and includes only the specs. Spec
findings appear on their own `TS (specs)` line.

ESLint **type-aware-lints** specs for zero-config (managed) extensions — the spec leaves are
referenced from the generated root `tsconfig.json`, so typescript-eslint's project service
finds them. Bridged plugins (own committed config extending `.shopware-admin/`) keep
syntactic + jest-globals linting on specs; `vue-tsc` still type-checks those specs, only
ESLint's type-aware rules are off there.

Type-checking specs surfaces findings that were previously invisible. Record them once with
`--update-baseline` (see below) so the check fails only on new ones.

## Baseline

A big plugin adopting the tooling can produce hundreds of findings at once, which makes an
exit-1 check useless. Record the current findings as a baseline; the check then reports them
separately and fails only on **new** findings (PHPStan-style):

```bash
composer admin:check-extensions -- --only=MyPlugin --update-baseline
```

This writes a committed `.shopware-admin-baseline.json` at the plugin root (custom/plugins
only). Paths inside are stored relative to the plugin root, so the baseline travels with the
plugin. **Commit it.** Later checks show `N new · M baselined`, and a fully baselined plugin
reads green. Matching ignores line and column (file + code/rule + message), so a finding
survives unrelated line drift. Findings that no longer occur are reported as stale and pruned
on the next `--update-baseline`. Fix findings and re-run `--update-baseline` to shrink the
baseline; it cannot be combined with `--fix` (fix first, then record).

Vendor-installed extensions carry no baseline — their findings are already non-fatal unless
`--strict-vendor`.

## Troubleshooting

| Symptom | Cause → fix |
| --- | --- |
| A plugin is missing from the extension list | Discovery reads `var/plugins.json`, which neither `plugin:install` nor `cache:clear` refresh. Run `bin/console bundle:dump`. |
| `⊘ skipped — own tsconfig does not reach the Shopware type surface` | The printed `why:` names the exact cause: an own `"files"` array replaces the bridge's type-surface injection (remove it), the `extends` chain never reaches the preset (add `"extends": "./.shopware-admin/tsconfig.json"`), or there is no bridge yet (`-- --shim=<name>`). |
| `⊘ skipped — own config does not compose the Shopware factory` | Compose the bridge: `import shopware from './.shopware-admin/eslint.mjs'; export default [ ...shopware ];` |
| `⊘ blocked (entity schema missing)` | Run `composer admin:generate-entity-schema-types`; TypeScript checks refuse to run against the empty-schema stub. |
| `Duplicate identifier` errors after bridging | Your plugin's own `global.types.ts` re-declares parts of the preset surface — prune the duplicates. |
| `Cannot find module 'axios'` (or another host package) | The preset drops the old `"*" → node_modules` fallback. Map the package in `tsconfig.aliases.json` (see above). |
| Three `Script … returned with error code 1` lines after a failing check | Composer prints these for every failing composer script; the check's own summary line above them is the verdict. |

## The type surface

The type surface is the live installed Administration types — not a curated
API file. `@deprecated` / `@internal` / `@private` JSDoc annotations mark the
API boundary in the source; ESLint rules (`@typescript-eslint/no-deprecated`,
`sw-deprecation-rules/*`) turn them into feedback. Internal plugins that
deliberately use internal APIs can lower `internalApiSeverity` or disable
individual rules in their own config. The live types are not a stability
promise; the annotations plus these rules are the communication channel.

## Work items

- A dedicated lint rule that flags usage of `@internal` / `@private` members
  from extension code does not exist yet; today only `@deprecated` usage is
  detected (via `@typescript-eslint/no-deprecated`).
