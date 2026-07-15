---
title: Add opt-in TypeScript and ESLint tooling for Administration extensions
issue: #14040
---
# Administration
* Added `composer admin:setup-extension-tooling` — discovers every installed extension with Administration sources (from `var/plugins.json`) and generates disposable, marker-owned tooling state: one tsconfig per extension under `var/admin-extension-tooling/`, a solution-style root `tsconfig.json`, a scoped root `eslint.config.mjs`, and IDE bootstraps for VS Code and Zed (PhpStorm setup is printed). Optional `--shim=<name>` writes a self-ignoring `.shopware-admin/` shim into a plugin for committed configs; `--check` validates without writing; `--explain` prints the discovered state.
* Added `composer admin:check-extensions` — type-checks (vue-tsc) and lints (ESLint) all installed Administration extensions with the Administration's own pinned toolchain and native tool output. Extension configs that do not compose the Shopware preset are visibly skipped as `unmanaged`; findings in `vendor/` extensions report non-fatally unless `--strict-vendor` is set. Supports `--only=<name>`.
* Added `src/Administration/Resources/app/administration/extension-tooling/` — the committed contract: `tsconfig.base.json` (strict preset), `admin-types.d.ts` (the live type surface: `global.types.ts` + generated entity schema + html shim), `eslint.mjs` (parameterized flat-config factory `shopwareAdminExtension()`), `legacy-twig.mjs` (Twig template lint preset), and `host-modules.json` (bare modules the host runtime provides; currently `vue`).
* Added `vue-tsc` as an Administration devDependency.
* Both commands are strictly opt-in: no default build, watch, init, or CI flow invokes them.
