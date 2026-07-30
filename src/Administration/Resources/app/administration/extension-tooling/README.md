# Administration Extension Tooling

> **⚠ Experimental — not covered by the backwards-compatibility promise.**
>
> Anything here can be refactored in any release, including a patch, without a
> deprecation cycle: command names and options, the layout of every generated
> file, the manifest schema, exit codes, report wording. Stabilization is targeted
> for v6.8.0 (`@experimental stableVersion:v6.8.0 feature:ADMIN_EXTENSION_TOOLING`
> in the source).
>
> That is safe because **re-running setup is the migration path for all of it**:
> every generated file is either tool-owned (rewritten each run) or marker-owned
> (never overwritten once a human edits it), and your committed configs stay yours
> as long as they keep composing the bridge. And **opting out costs nothing** —
> the only automated hook, the `composer setup` step, is a no-op unless
> `ADMIN_EXTENSION_TOOLING=1` is set. So do not hand-edit generated files or
> automate on top of the generated layout: drive it through the commands.

This folder ships the TypeScript and ESLint contract for Administration
extensions. It travels with the Administration package, so a platform checkout, a
Composer install and a production shop carry the identical toolchain, matching the
installed Shopware version. Only the way you invoke setup differs.

## What lives here

| File | Purpose |
| --- | --- |
| `tsconfig.base.json` | Strict TypeScript preset for extension code (ESNext, Bundler resolution, `noEmit`). Resolves `vue`, `@vue/*` and `src/*` into the installed Administration. |
| `admin-types.d.ts` | The one type surface: the live `global.types.ts`, the generated `entity-schema-definition.d.ts` and `html-shim.d.ts`. Injected into every extension program via `files`. |
| `eslint.mjs` | Flat-config factory `shopwareAdminExtension(options)`; all plugins resolve from the Administration's `node_modules`. The Administration's own config composes the same factory (via host options such as `srcImportBoundary: false` and `specFiles: 'typed'`), so host and extension rules cannot drift apart. |
| `legacy-twig.mjs` | Lint preset for legacy `.html.twig` component templates (Twig-Vue processor). |
| `host-modules.json` | The bare modules the Administration provides to extensions at runtime. v1: `vue` only, matching the Vite externals plugin. Adding one here also means adding it to `tsconfig.base.json` `paths`. |

## How extensions use it

You normally do not reference this folder manually.

**In a Composer/Flex-installed shop** (the [official installation
guide](https://developer.shopware.com/docs/guides/installation/) layout, with the
Administration under `vendor/shopware/administration`) drive it through
`bin/console`:

```bash
# One-time: install the Administration's Node dependencies (not part of the
# Composer package). Re-run only after a Shopware update.
( cd vendor/shopware/administration/Resources/app/administration && npm ci )

bin/console administration:setup-extension-tooling         # generate configs for all installed extensions
bin/console administration:generate-entity-schema-types    # (re)generate the entity-schema types
```

**In a platform (monorepo) checkout** the equivalent Composer scripts are wired
up. From the project root:

```bash
composer admin:setup-extension-tooling          # generate configs for all installed extensions
composer admin:setup-extension-tooling:check    # guaranteed-safe dry-run (writes nothing; exit 1 on drift)
composer admin:setup-extension-tooling -- --help
```

⚠ Options need the `--` separator — Composer silently swallows anything before it,
so `composer admin:setup-extension-tooling --check` runs a plain setup rather than
a dry-run. The `:check` alias cannot mutate files either way; prefer it in CI. The
tooling prints its next steps in the current layout's command form, so they are
copy-pasteable as-is.

Setup discovers every installed extension with Administration sources from
`var/plugins.json` (refresh it with `bin/console bundle:dump` after installing or
activating a plugin) and generates root `tsconfig.json` / `eslint.config.mjs`
projections, so IDEs and a shop-wide `eslint .` see every extension. A
marker-fenced block in the project `.gitignore` covers them (skipped when the
entries already exist; opt out with `--no-gitignore`).

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
  and rules — as long as the `extends`/import stays.
- Existing configs are never overwritten: setup leaves them alone and prints the
  one line to add so they compose the preset too.
- One bridge per directory that owns a config: a multi-bundle package with one
  shared config gets a single shared bridge, independent per-root configs get one
  each. `-- --root-config=<Extension>:<dir>` forces a shared bridge for a layout
  the grouping cannot infer, and the config scaffolded there makes that choice
  self-perpetuating.
- A bridge that cannot be written (a read-only vendor directory, say) degrades to
  a warning — those sources stay covered by the root `tsconfig.json`.

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

## Generated files — what to commit, what to ignore

Only the **committable** files belong in the plugin repository. Nothing written
under `vendor/` is ever committed: a composer update removes those files and
re-running setup restores them.

| File | Kind | Commit? | Notes |
| --- | --- | --- | --- |
| `var/admin-extension-tooling/manifest.json` | disposable host state | no | Rewritten every run; git-ignored in a shop. |
| Project-root `tsconfig.json` / `eslint.config.mjs` / `.vscode/` / `.zed/` | disposable host projections | no | The shop-wide IDE/CLI view; the tsconfig covers whatever no extension config governs. Marker-owned and git-ignored (the platform monorepo commits its own, so setup stands down there). |
| `<plugin>/…/.shopware/` (`tsconfig.json`, `eslint.mjs`, `.gitignore`, `README.md`) | git-ignored bridge | **no** | Machine-specific paths into the installed Administration; self-ignoring (`*`) and self-explaining. One per directory that owns a config. |
| `<plugin>/…/tsconfig.json` + `eslint.config.mjs` (scaffolded when absent) | plugin config | **yes** under `custom/plugins/` | Small files that extend/compose the bridge. Edit freely; keep the `extends`/import. |
| `<plugin>/tsconfig.aliases.json` | plugin config | **yes** | Your path aliases; merged into the bridge. |

`--check` (or the `:check` alias) labels each planned file `[git-ignored bridge]`,
`[commit this]` or `[local — restored by re-running setup]`, so you can see the
split before anything is written.

## Troubleshooting

| Symptom | Cause → fix |
| --- | --- |
| A plugin is missing from the extension list | Discovery reads `var/plugins.json`, which neither `plugin:install` nor `cache:clear` refresh. Run `bin/console bundle:dump` — and note a new plugin must be installed and active first: `bin/console plugin:refresh && bin/console plugin:install --activate <Name>`. |
| `Duplicate identifier` errors after bridging | Your plugin's own `global.types.ts` re-declares parts of the preset surface — prune the duplicates. |
| `Cannot find module 'axios'` (or another host package) | The preset drops the old `"*" → node_modules` fallback. Map the package in `tsconfig.aliases.json` (above). |
| Trailing `Script …` lines after a failing run | Composer echoes the script chain on a non-zero exit. The report's own summary above them is the verdict. |

## The type surface

The type surface is the live installed Administration types, not a curated API
file, so it is not a stability promise on its own: the annotation is.
`@deprecated` / `@internal` / `@private` JSDoc marks the boundary in the source
and ESLint rules (`@typescript-eslint/no-deprecated`, `sw-deprecation-rules/*`)
turn it into feedback — internal plugins that deliberately use internal APIs can
lower `internalApiSeverity` or disable individual rules. Today only `@deprecated`
usage is detected; a rule for `@internal` / `@private` usage does not exist yet.
