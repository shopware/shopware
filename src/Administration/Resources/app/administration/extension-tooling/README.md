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
composer admin:check-extensions          # type-check + lint all installed extensions
```

Setup discovers every installed extension with Administration sources (from
`var/plugins.json`), generates one tsconfig per extension under
`var/admin-extension-tooling/`, plus root `tsconfig.json` / `eslint.config.mjs`
projections so IDEs see exactly what the check command checks.

- **Zero-config**: a plugin with no config files at all is fully covered.
- **Committed configs**: generate a shim with
  `composer admin:setup-extension-tooling -- --shim=<TechnicalName>` and commit
  files that extend `.shopware-admin/` inside your plugin. The shim holds the
  only machine-specific path and ignores itself via its own `.gitignore`.

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
