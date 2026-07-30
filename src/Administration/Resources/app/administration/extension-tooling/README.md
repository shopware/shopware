# Administration Extension Tooling

> **⚠ Experimental — not covered by the backwards-compatibility promise.**
>
> This toolchain is shipped early to gather feedback while it is still being
> shaped, so it can be refactored in any release — including a patch — without a
> deprecation cycle. No stable version is targeted yet; the marker is removed
> once the surfaces below have settled in practice.
>
> **What can change without notice**
>
> - the command names (`admin:setup-extension-tooling`,
>   `administration:setup-extension-tooling`) and every option they accept
> - the layout and contents of everything generated under
>   `var/admin-extension-tooling/`, the root `tsconfig.json` / `eslint.config.mjs`
>   projections, and the `.shopware/` bridge
> - the `var/admin-extension-tooling/manifest.json` schema (it carries a `version`
>   field precisely so it can change)
> - the exit codes, the report wording, and the module layout under
>   `scripts/extensionTooling/`
>
> **What is safe to rely on**
>
> - **Re-running setup.** The generated files are disposable by design: every one
>   is either tool-owned (rewritten on each run) or marker-owned (never
>   overwritten once a human edits it). Re-running setup after a Shopware update
>   is the supported migration path for every change listed above.
> - **Your own committed configs.** A bridged extension's `tsconfig.json` and
>   `eslint.config.mjs` are yours; the tooling only asks that they keep the
>   `extends` pointing at the generated bridge.
> - **Opting out costs nothing.** No build, watch, init, or CI pipeline invokes
>   this tooling. Not running it leaves a project exactly as it was.
>
> Do not hand-edit generated files and do not build automation on top of the
> generated layout — drive it through the commands instead, and re-run them after
> updating Shopware.

This folder ships the TypeScript and ESLint contract for Administration
extensions. It travels with the Administration package, so a platform checkout,
a Composer install, and a production shop all carry the identical toolchain —
matching the installed Shopware version. Only the way you invoke the setup
command differs between a platform checkout and a Composer/Flex install (both
are covered below).

## What lives here

| File | Purpose |
| --- | --- |
| `tsconfig.base.json` | Strict TypeScript preset for extension code (ESNext, Bundler resolution, `noEmit`). Resolves `vue`, `@vue/*`, and `src/*` into the installed Administration. |
| `admin-types.d.ts` | The one type surface: imports the live `global.types.ts`, the generated `entity-schema-definition.d.ts`, and `html-shim.d.ts`. Injected into every extension program via `files`. |
| `eslint.mjs` | Parameterized flat-config factory `shopwareAdminExtension(options)`. All plugins resolve from the Administration's `node_modules`. |
| `legacy-twig.mjs` | Lint preset for legacy `.html.twig` component templates (Twig-Vue processor). |
| `host-modules.json` | Declares the bare modules the Administration host provides to extensions at runtime. v1: `vue` only — the Vite externals plugin replaces exactly the bare `vue` import. If a module is added there, it must be added here and to `tsconfig.base.json` `paths` in the same change. |

## How extensions use it

You normally do not reference this folder manually.

**In a Composer/Flex-installed shop** (the [official installation guide](https://developer.shopware.com/docs/guides/installation/) layout, where the Administration lives under `vendor/shopware/administration`), drive the toolchain through `bin/console`:

```bash
# One-time: install the Administration's Node dependencies (they are not part
# of the Composer package). Re-run only after a Shopware update.
( cd vendor/shopware/administration/Resources/app/administration && npm ci )

bin/console administration:setup-extension-tooling            # generate configs for all installed extensions
bin/console administration:generate-entity-schema-types       # (re)generate the entity-schema types
```

The console commands resolve the shop root automatically and forward everything
after `--` to the toolchain, so every option below works the same way. The
tooling also prints these `bin/console` forms in its own guidance when it runs
in a vendor layout, so any next step it suggests is copy-pasteable as-is.

**In a platform (monorepo) checkout**, the equivalent Composer scripts are wired up. From the project root:

```bash
composer admin:setup-extension-tooling   # generate configs for all installed extensions
composer admin:setup-extension-tooling:check   # guaranteed-safe dry-run (writes nothing; exit 1 on drift)
composer admin:setup-extension-tooling -- --help                # full option reference
```

⚠ Options always need the `--` separator — Composer silently swallows anything before it
(`composer admin:setup-extension-tooling --check` runs a plain setup, not a dry-run). For a
dry-run that **cannot** mutate files regardless of the separator, use the dedicated
`composer admin:setup-extension-tooling:check` alias — prefer it in CI.

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

  This writes a git-ignored `.shopware/` bridge (which holds the machine-specific paths and
  composes the preset) and — if the plugin has no config yet — two small **committable** files at
  the plugin's administration folder that just extend it:

  ```jsonc
  // tsconfig.json
  { "extends": "./.shopware/tsconfig.json", "include": ["src/**/*.ts", "src/**/*.vue"] }
  ```
  ```js
  // eslint.config.mjs
  import shopware from './.shopware/eslint.mjs';
  export default [ ...shopware, /* your own rules */ ];
  ```

  Commit those two files and edit them freely — add your own options/rules — as long as the
  `extends`/import stays. The tool never overwrites them: if you already have configs, setup
  leaves them alone and prints the one line to add so they compose the preset too.

## Own path aliases (`tsconfig.aliases.json`)

TypeScript replaces `paths` **wholesale** across `extends`: declaring
`"paths": { "MyPlugin/*": ["src/*"] }` in your own tsconfig would erase the preset's
`vue` / `src/*` mappings. Declare aliases in a committed `tsconfig.aliases.json` next to
your config instead:

```jsonc
{ "MyPlugin/*": ["src/*"] }
```

Re-run `composer admin:setup-extension-tooling -- --shim=<TechnicalName>` afterwards: the
generated `.shopware/` bridge becomes the single `paths` declarer and merges your
aliases with the preset's host paths (targets resolve relative to the plugin's
administration folder). The same mechanism covers type-only imports of host packages,
e.g. `{ "axios": ["../../../../../../../src/Administration/Resources/app/administration/node_modules/axios"] }`.

## Generated files — what to commit, what to ignore

Setup and `--shim` produce three kinds of file. Only the **committable** ones belong in the
plugin repository:

| File | Kind | Commit? | Notes |
| --- | --- | --- | --- |
| `var/admin-extension-tooling/**` (leaf tsconfigs, manifest) | disposable host state | no | Regenerated every run; git-ignored in a shop. |
| Project-root `tsconfig.json` / `eslint.config.mjs` / `.vscode/` / `.zed/` | disposable host projections | no | IDE/CLI view of the whole shop; marker-owned, git-ignored (the platform monorepo commits its own, so setup stands down there). |
| `<plugin>/…/.shopware/` (bridge `tsconfig.json`, `eslint.mjs`, `.gitignore`, `README.md`) | git-ignored bridge | **no** | Machine-specific paths into the installed Administration; self-ignoring (`*`) and self-explaining (generated README). One per shimmed root, or one beside the package config in root-config mode. |
| `<plugin>/…/tsconfig.json` + `eslint.config.mjs` (scaffolded when absent) | committable plugin config | **yes** | Small files that just extend/compose the bridge. Edit freely; keep the `extends`/import. |
| `<plugin>/tsconfig.aliases.json` | committable plugin config | **yes** | Your path aliases; merged into the bridge. |

For a **single-root** plugin that's one bridge + one committable `tsconfig.json`/`eslint.config.mjs`
pair. For a **multi-root** package in root-config mode it's one bridge + one committable config
pair beside the package config (not one per bundle). `setup … --check` (or the
`admin:setup-extension-tooling:check` alias) labels each planned file as `[git-ignored bridge]`
or `[commit this]` so you can see the split before writing.

## Troubleshooting

| Symptom | Cause → fix |
| --- | --- |
| A plugin is missing from the extension list | Discovery reads `var/plugins.json`, which neither `plugin:install` nor `cache:clear` refresh. Run `bin/console bundle:dump`. A freshly created plugin must be installed and active before `bundle:dump` lists it: `bin/console plugin:refresh && bin/console plugin:install --activate <Name>`. |
| `Duplicate identifier` errors after bridging | Your plugin's own `global.types.ts` re-declares parts of the preset surface — prune the duplicates. |
| `Cannot find module 'axios'` (or another host package) | The preset drops the old `"*" → node_modules` fallback. Map the package in `tsconfig.aliases.json` (see above). |
| Trailing `Script …` lines after a failing run | Composer echoes the script chain on a non-zero exit (the command flattens this to the minimum two lines). The report's own summary above them is the verdict. |

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
