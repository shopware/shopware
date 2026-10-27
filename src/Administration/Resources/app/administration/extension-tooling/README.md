# Administration Extension Tooling

This folder ships the TypeScript, ESLint and Jest contract for Administration
extensions. It travels with the Administration package, so a platform checkout,
a Composer install, and a production shop all carry the identical toolchain —
matching the installed Shopware version. Only the way you invoke the commands
differs between a platform checkout and a Composer/Flex install (both are covered
below).

## What lives here

| File | Purpose |
| --- | --- |
| `tsconfig.base.json` | Strict TypeScript preset for extension code (ESNext, Bundler resolution, `noEmit`). Resolves `vue`, `@vue/*`, and `src/*` into the installed Administration. |
| `admin-types.d.ts` | The one type surface: imports the live `global.types.ts`, the generated `entity-schema-definition.d.ts`, `html-shim.d.ts`, and `shopware-virtual-modules.d.ts`. Injected into every extension program via `files`. |
| `eslint.mjs` | Parameterized flat-config factory `shopwareAdminExtension(options)`. All plugins resolve from the Administration's `node_modules`. |
| `jest.mjs` | Jest config factory `shopwareAdminJest(options)`: the Administration's test harness with every path resolved into the installed Administration. The Administration's own Jest config composes it too. |
| `legacy-twig.mjs` | Lint preset for legacy `.html.twig` component templates (Twig-Vue processor). |
| `host-modules.json` | Declares the bare modules the Administration host provides to extensions at runtime. v1: `vue` only — the Vite externals plugin replaces exactly the bare `vue` import. If a module is added there, it must be added here and to `tsconfig.base.json` `paths` in the same change. |

The `shopware:*` modules (`shopware:utils`, `shopware:data`, `shopware:composables`, `shopware:mixins`,
`shopware:stores`) are not host modules. A Vite plugin generates them from the global `Shopware` object, and
`admin-types.d.ts` provides their declarations. Run `composer admin:generate-shopware-modules` after a
source contract changes. Do not add these modules to this table. See
[`shopware:*` Modules](../technical-docs/05-global-object/04-virtual-modules.md).

## How extensions use it

You normally do not reference this folder manually.

**In a Composer/Flex-installed shop** (the [official installation guide](https://developer.shopware.com/docs/guides/installation/) layout, where the Administration lives under `vendor/shopware/administration`), drive the toolchain through `bin/console`:

```bash
# One-time: install the Administration's Node dependencies (they are not part
# of the Composer package). Re-run only after a Shopware update.
( cd vendor/shopware/administration/Resources/app/administration && npm ci )

bin/console administration:setup-extension-tooling              # generate configs for all installed extensions
bin/console administration:check-extensions -- --only=MyPlugin  # type-check + lint your plugin
bin/console administration:check-extensions -- --all            # check every extension
bin/console administration:test-extensions -- --only=MyPlugin   # run your plugin's specs (see Running specs with Jest)
bin/console administration:generate-entity-schema-types         # (re)generate the entity-schema types
```

The console commands resolve the shop root automatically and forward everything
after `--` to the toolchain, so every option below works the same way. The
tooling also prints these `bin/console` forms in its own guidance when it runs in
a vendor layout, so any next step it suggests is copy-pasteable as-is.

**In a platform (monorepo) checkout**, the equivalent Composer scripts are wired up. From the project root:

```bash
composer admin:setup-extension-tooling          # generate configs for all installed extensions
composer admin:setup-extension-tooling:check    # guaranteed-safe dry-run (writes nothing; exit 1 on drift)
composer admin:check-extensions                 # pick extensions to type-check + lint (interactive picker)
composer admin:check-extensions -- --only=SwagPayPal,MyPlugin   # check specific ones, no prompt
composer admin:check-extensions -- --all                        # check every extension, no prompt
composer admin:check-extensions -- --only=MyPlugin --fix        # apply ESLint autofixes
composer admin:check-extensions -- --only=MyPlugin --update-baseline  # record current findings (see Baseline)
composer admin:check-extensions -- --all --fail-on-skipped      # CI: fail if any writable extension is not checked
composer admin:test-extensions -- --only=MyPlugin               # run your plugin's specs with the Administration's Jest
composer admin:test-extensions -- --only=MyPlugin -- --watch    # Jest options go after a second --
composer admin:setup-extension-tooling -- --help                # full option reference
```

⚠ Options always need the `--` separator — Composer silently swallows anything before it
(`composer admin:setup-extension-tooling --check` runs a plain setup, not a dry-run). For a
dry-run that **cannot** mutate files regardless of the separator, use the dedicated
`composer admin:setup-extension-tooling:check` alias — prefer it in CI.

In an interactive terminal `admin:check-extensions` opens a numbered picker (accepts
numbers, ranges, `a` for all, `w` for writable-only). Non-interactive shells (CI, piped)
check all extensions. `--verbose` additionally prints the underlying tool output for passing
and skipped extensions; `--show-commands` prints the exact vue-tsc/ESLint invocation per
extension; `--fix` forwards to ESLint (vendor-installed extensions only when named via
`--only`).

A writable extension whose own config does not compose the preset is visibly skipped (never
silently green), and by default the run still exits 0 with a prominent yellow warning so
incremental adoption stays possible. **In CI, add `--fail-on-skipped`**: it makes any skipped
or blocked writable extension exit 1, so `exit 0` cannot mean "checked nothing" (vendor
extensions keep their separate `--strict-vendor` policy). A partially unknown `--only`
selection (e.g. a renamed extension) fails the whole run and names the unknown entries rather
than silently checking only the ones that still match.

Setup discovers every installed extension with Administration sources from
`var/plugins.json` (refresh it with `bin/console bundle:dump` after installing or
activating a plugin) and generates root `tsconfig.json` / `eslint.config.mjs`
projections, so IDEs and a shop-wide `eslint .` see exactly what the check command
checks. A marker-fenced block in the project `.gitignore` covers them (skipped when
the entries already exist; opt out with `--no-gitignore`).

Every discovered extension is **bridged automatically** in the same run — custom
and vendor-installed alike, no separate command. There is no bridge-less mode:
linting and type-checking against the Administration only work through the
generated configs.

- A git-ignored, self-explaining `.shopware/` bridge lands beside each extension's
  Administration folder, holding the machine-specific paths and composing the
  preset.
- An extension without configs gets two small ones scaffolded beside the bridge:

  ```jsonc
  // tsconfig.json
  { "extends": "./.shopware/tsconfig.json", "include": ["src/**/*.ts", "src/**/*.vue"] }
  ```
  ```js
  // eslint.config.mjs
  import shopware from './.shopware/eslint.mjs';
  export default [ ...shopware, /* your own rules */ ];
  ```

  Under `custom/plugins/` commit them and edit them freely — add your own options
  and rules — as long as the `extends`/import stays. The check uses your committed
  configs, so what runs is what you see.
- Existing configs are never overwritten: setup leaves them alone, and the check
  prints a `why:` naming what is missing plus a `fix:` with the exact edit to add.
- One bridge per directory that owns a config: a multi-bundle package with one
  shared config gets a single shared bridge, independent per-root configs get one
  each. `-- --root-config=<Extension>:<dir>` forces a shared bridge for a layout
  the grouping cannot infer, and the config scaffolded there makes that choice
  self-perpetuating.
- A bridge that cannot be written (a read-only vendor directory, say) degrades to
  a warning — those sources stay covered by the root `tsconfig.json`.

## Native-setup components

Native-setup authoring works out of the box — no extra config to copy. The
generated ESLint config declares the compile-time macro globals (`swDefinePublic`,
`swDefineOverride`, `useSwPreviousState`, `useSwProps`, `useSwContext`) and turns on
the two native-setup guards (`sw-core-rules/valid-shopware-setup`,
`sw-core-rules/native-setup-filename`), and the type surface (`admin-types.d.ts`)
carries the macro declarations so they type-check. Both flow through the same
package resolution as the rest of the preset, so a dependency bump can never leave
a stale hand-copied path behind.

## Code-quality rules

The generated ESLint config reports redundant code with `no-else-return`, `no-useless-return`, `no-lonely-if` and `no-unneeded-ternary`.
TypeScript and Vue files also get `@typescript-eslint/no-unnecessary-boolean-literal-compare`, `@typescript-eslint/no-unnecessary-template-expression` and `@typescript-eslint/no-unnecessary-type-conversion`.
The Administration lints its own sources with the same rules.
All of them have an autofix, so `--fix` resolves the findings.
To turn one off, set it to `'off'` in your own `eslint.config.mjs` after the `...shopware` spread.

## Own path aliases (`tsconfig.aliases.json`)

TypeScript replaces `paths` **wholesale** across `extends`, so declaring
`"paths": { "MyPlugin/*": ["src/*"] }` in your own tsconfig would erase the
preset's `vue` / `src/*` mappings. Declare them in a committed
`tsconfig.aliases.json` next to your config instead:

```jsonc
{ "MyPlugin/*": ["src/*"] }
```

Re-run setup afterwards: the bridge becomes the single `paths` declarer and merges
your aliases with the preset's host paths, resolving targets relative to the alias
file's own directory. The same mechanism covers type-only imports of host packages
— map them to a path under the Administration's `node_modules`.

## Test files

Spec files (`**/*.spec.{ts,tsx,js}`) are type-checked by a **dedicated program** with jest
types, separate from the runtime program. The runtime config still excludes specs — its
preset sets `types: []` so the runtime globals stay runtime-only — and the check runs a
second `vue-tsc` pass over a generated spec tsconfig (`.shopware/tsconfig.specs.json`) that
composes the runtime bridge, injects `spec-types.d.ts` (jest `describe`/`it`/`expect`, …),
and includes only the specs. Spec findings appear on their own `TS (specs)` line.

ESLint lints specs syntactically with the jest globals available (`describe`, `it`, `expect`,
…); its **type-aware** rules stay off for specs, because the generated bridge configs are not
solution-style project references and typescript-eslint's project service has no spec program
to resolve them against. `vue-tsc` is what type-checks specs — ESLint is not.

Type-checking specs surfaces findings that were previously invisible. Record them once with
`--update-baseline` (see below) so the check fails only on new ones.

## Running specs with Jest

The bridge also carries `.shopware/jest.mjs`.
It hands your specs the harness the Administration's own specs run on:
the `shopware:*` virtual modules, the registered feature flags behind `Shopware.Feature.triggerDeprecationOrThrow()`,
`it.deprecated()` and `it.activeFeatureFlags()`, the global `Shopware` object, the Vue and Twig transforms,
and one shared copy of Vue, `@vue/test-utils`, Pinia, `vue-i18n`, `vue-router`, lodash and the Meteor component library.
When the harness changes, your specs pick it up with the next Shopware version, without an edit to your config.

Setup does not scaffold a Jest config, because not every extension has specs.
Opt in with a committed `jest.config.mjs` beside the bridge:

```js
import { shopwareAdminJestConfig } from './.shopware/jest.mjs';

export default shopwareAdminJestConfig({
    moduleNameMapper: { '^MyPlugin/(.*)$': '<rootDir>/src/$1' },
    setupFilesAfterEnv: ['<rootDir>/test/setup.js'],
});
```

`<rootDir>` is the folder that holds `.shopware/`.
A config somewhere else, for example a shared test folder of a multi-bundle plugin,
imports `shopwareAdminJest` from the bridge or from `extension-tooling/jest.mjs` and passes its own `rootDir`.

The factory accepts an explicit allowlist of Jest options, and throws on any other option with the list of accepted ones.
The options that build the harness are merged, so your config cannot drop it by accident:

| Option | Merged how |
| --- | --- |
| `setupFiles`, `setupFilesAfterEnv` | Yours run after the Administration's, so `Shopware` and the feature flags are ready. |
| `moduleNameMapper`, `transform`, `moduleDirectories` | Yours are tried first, so your alias or dependency wins over the host's. |
| `globals`, `testEnvironmentOptions` | Merged; your values win, except `globals.adminPath` and `globals['vue-jest'].tsConfig`, which stay pinned to the installed Administration. |

The other accepted options replace the default as a whole,
for example `roots`, `testMatch`, `testPathIgnorePatterns`, `testTimeout`, `maxWorkers`, `reporters` and the `coverage*` options.
`transformIgnorePatterns` replaces the default too, so spread `HOST_TRANSFORM_IGNORE_PATTERNS` to keep the Administration's ESM packages transformed.
`.shopware/jest.mjs` exports it next to `shopwareAdminJest`:

```js
import { HOST_TRANSFORM_IGNORE_PATTERNS, shopwareAdminJest } from './.shopware/jest.mjs';

export default shopwareAdminJest({
    rootDir: import.meta.dirname,
    transformIgnorePatterns: [...HOST_TRANSFORM_IGNORE_PATTERNS, '<rootDir>/vendor/'],
});
```

Jest skips a file that matches any of the patterns.
So to transform an additional ESM package from `node_modules`, do not add a pattern next to the host's;
build one pattern from `HOST_TRANSFORMED_PACKAGES` and your package instead:

```js
import { HOST_TRANSFORMED_PACKAGES, shopwareAdminJest } from './.shopware/jest.mjs';

export default shopwareAdminJest({
    rootDir: import.meta.dirname,
    transformIgnorePatterns: [`/node_modules/(?!(${[...HOST_TRANSFORMED_PACKAGES, 'nanoid'].join('|')})/)`],
});
```

`REPLACEABLE_OPTIONS` in `extension-tooling/jest.mjs` holds the full list.
The harness owns `testEnvironment` and `resolver`, so the factory rejects both.
`it.activeFeatureFlags()` depends on the environment, and the resolver strips Vite query suffixes such as `?worker` from imports.

Run the specs with `test-extensions`, which uses the Administration's own Jest, so an extension needs no Jest installation of its own:

```bash
composer admin:test-extensions -- --only=MyPlugin                  # platform checkout
bin/console administration:test-extensions -- --only=MyPlugin      # Composer install
composer admin:test-extensions -- --only=MyPlugin -- --watch       # Jest options go after a second --
```

It runs every `jest.config.{js,ts,mjs,cjs}` it finds at the extension root, beside an Administration folder, or beside the extension's own tsconfig or ESLint config.
Without `--only` or `--all`, an interactive terminal offers a picker of the extensions that have a Jest config.
Elsewhere, and with `--all`, every such extension runs, vendor-installed ones included.
`--no-vendors` leaves out the extensions under `vendor/`, in `--all` and in the picker; `--only` names extensions by extension or bundle name.
Each Jest run starts with a `▶ <extension> · <jest config>` line, and the report ends with one line per Jest config.
A run in which no spec ran exits 1, so a CI job cannot pass without running a spec.
Jest's `--watch` and `--watchAll` need exactly one Jest config, so name one extension via `--only`; otherwise the command exits 2.
When Jest options go to more than one Jest config, the command adds `--passWithNoTests`,
so a spec path or name filter fails the run only on a failing spec, not on an extension without a matching spec.
The report marks such an extension with `–`, and the run still fails when no Jest config ran a spec; pass `--passWithNoTests` yourself to allow that.

Which Jest runs a config depends on the config:

- A config on the harness runs on the Administration's Jest, which the harness is built for.
  The command recognizes it by an import of `.shopware/jest.mjs` or `extension-tooling/jest.mjs`, or a call of `shopwareAdminJest`.
- Any other config runs on the Jest the extension installs between the config's folder and the extension root.
  The `▶` line and the report mark it with `(own Jest)`.
- When a `package.json` there declares Jest but it is not installed, the config does not run.
  The report marks it with `⊘` and names the `npm ci` or `npm install` that installs it;
  the run fails on it only when `--only` or the picker named its extension.
- A config with neither runs on the Administration's Jest.

An extension that installs its own Jest can keep running it directly, as long as it has the same major version as the Administration's.

The harness lives in the Administration's `test/` folder and works in a platform checkout and in a Composer install alike.
It reads two files that every installation generates itself.
Generate them before the first run and after every Shopware update:

```bash
bin/console administration:generate-entity-schema-types                 # test/_mocks_/entity-schema.json
( cd <administration> && npm run unit-setup )                            # test/_helper_/componentWrapper/component-imports.js
```

`test-extensions` generates the component import map itself when it is missing;
the entity schema and a stale import map still need the commands above.
When a file is still missing, the factory fails with the command that generates it.

## From JavaScript to TypeScript

A `.js` plugin is linted immediately, but TypeScript checks nothing — `checkJs` is off, so
`.js` sources are parsed, never type-checked (the check reports this honestly as
`0 TypeScript files`, not a green pass). To get real type-checking, migrate incrementally —
one file at a time, no big-bang rewrite:

1. **Rename** a `.js` source to `.ts` (or `.vue` for a component). Nothing else needs to
   change first — the generated bridge config already includes `.ts`.
2. **Re-run** `composer admin:check-extensions -- --only=<name>`. Expect a burst of
   `TS7006 … implicitly has an 'any' type` on un-annotated parameters (the preset is
   `strict`, so `noImplicitAny` is on) — this is normal for a fresh conversion.
3. **Annotate** parameters and return types. Use the global `Shopware` object and
   `Entity<'...'>` helpers rather than importing Administration internals (the runtime-contract
   rule forbids `src/*` imports).
4. **Expect ESLint findings to rise too**: a `.ts` file also gets the type-aware
   `@typescript-eslint/no-unsafe-*` rules that a `.js` file never triggered. That is coverage,
   not regression.
5. **Baseline the rest** if a large file produces too many findings at once (see below), then
   shrink the baseline over time.

There is no bulk `.js`→`.ts` codemod: an automatic rename-and-annotate would either produce
uncompilable code or silently hide real type errors, so conversion stays a deliberate,
per-file, developer-driven step. `--fix` still applies the ESLint autofixes and Shopware
deprecation codemods on whatever files exist.

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

A baselined finding is hidden, not gone. The passed line names its count and the flag that
brings it back — `(12 baselined — show with -- --verbose)`; on a failing run the report lists
`new — must fix to pass` and `baselined — suppressed` as two separate groups, so the raw tool
output below them never has to be read to tell which is which. To un-hide everything
permanently, delete the plugin's `.shopware-admin-baseline.json`.

Only a writable `custom/plugins` extension can hold a baseline; the command says so instead of
recording nothing:

- **Vendor-installed extensions** carry no baseline — their findings are already non-fatal
  unless `--strict-vendor`.
- **In-repo bundles** (`src/Storefront`, `src/Administration`, …) are writable and their
  findings *are* fatal, but they are not plugins and must not collect per-developer debt files.
  `--update-baseline` warns and the check keeps failing them — fix the findings instead.

> **Composer-managed plugins are `vendor`.** A plugin installed through Composer — including one
> developed under `custom/static-plugins/` and pulled in via a path repository — resolves through
> `vendor/`, so the toolchain classifies it as a read-only `vendor` extension: findings are
> non-fatal by default, and the baseline is not available for it. Gate such plugins in CI with
> `--strict-vendor`. For full first-party tooling (fatal findings, baseline), develop the plugin
> under `custom/plugins/`.

## Generated files — what to commit, what to ignore

Only the **committable** files belong in the plugin repository. Nothing written
under `vendor/` is ever committed: a composer update removes those files and
re-running setup restores them.

| File | Kind | Commit? | Notes |
| --- | --- | --- | --- |
| `var/admin-extension-tooling/manifest.json` | disposable host state | no | Rewritten every run; git-ignored in a shop. |
| Project-root `tsconfig.json` / `eslint.config.mjs` / `.vscode/` / `.zed/` | disposable host projections | no | The shop-wide IDE/CLI view; the tsconfig covers whatever no extension config governs. Marker-owned and git-ignored (the platform monorepo commits its own, so setup stands down there). |
| `<plugin>/…/.shopware/` (`tsconfig.json`, `eslint.mjs`, `jest.mjs`, `.gitignore`, `README.md`) | git-ignored bridge | **no** | Machine-specific paths into the installed Administration; self-ignoring (`*`) and self-explaining. One per directory that owns a config. |
| `<plugin>/…/tsconfig.json` + `eslint.config.mjs` (scaffolded when absent) | plugin config | **yes** under `custom/plugins/` | Small files that extend/compose the bridge. Edit freely; keep the `extends`/import. |
| `<plugin>/tsconfig.aliases.json` | plugin config | **yes** | Your path aliases; merged into the bridge. |
| `<plugin>/.shopware-admin-baseline.json` | committable plugin data | **yes** | Findings baseline; travels with the plugin. |

Every run labels the files it lists by that lifecycle — `[commit this]`,
`[local — restored by re-running setup]`, `[project-root projection — git-ignored]`,
`[disposable — regenerated by setup]`, and one summary line counting the
`git-ignored .shopware/ bridge file(s)`. Above the list, a `project-root projection`
line names the generated root `tsconfig.json` / `eslint.config.mjs` and the source
roots each one covers, so they are stated even on a re-run that changes nothing.
With `--check` (or the `:check` alias) you see that whole split before anything is
written.

## Troubleshooting

| Symptom | Cause → fix |
| --- | --- |
| A plugin is missing from the extension list | Discovery reads `var/plugins.json`, which neither `plugin:install` nor `cache:clear` refresh. Run `bin/console bundle:dump`. A freshly created plugin must be installed and active before `bundle:dump` lists it: `bin/console plugin:refresh && bin/console plugin:install --activate <Name>`. |
| `⊘ skipped — own tsconfig does not reach the Shopware type surface` | The printed `why:` names the exact cause: an own `"files"` array replaces the bridge's type-surface injection (remove it), or the `extends` chain never reaches the preset (add `"extends": "./.shopware/tsconfig.json"`). Re-run setup if the `.shopware/` bridge is missing. |
| `⊘ skipped — own config does not compose the Shopware factory` | Compose the bridge: `import shopware from './.shopware/eslint.mjs'; export default [ ...shopware ];` |
| `⊘ blocked (entity schema missing)` | Run `composer admin:generate-entity-schema-types`; TypeScript checks refuse to run against the empty-schema stub. |
| `Duplicate identifier` errors after bridging | Your plugin's own `global.types.ts` re-declares parts of the preset surface — prune the duplicates. |
| `Cannot find module 'axios'` (or another host package) | The preset drops the old `"*" → node_modules` fallback. Map the package in `tsconfig.aliases.json` (see above). |
| Trailing `Script …` lines after a failing check | Composer echoes the script chain on a non-zero exit (the command flattens this to the minimum two lines). The check's own summary line above them is the verdict. |

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
