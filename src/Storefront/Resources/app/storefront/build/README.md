# Storefront Build (Vite) Overview

This directory contains build-time tooling for the Storefront frontend.

The current component pipeline is Vite-based and covers:

- building the shared `shopware` runtime module
- building Twig component JS/TS and styles (`.scss` or `.css`)
- powering the Storefront Vite dev server

## High-level pipeline

From project root, the full Storefront build is:

```bash
composer build:js:storefront
```

At a high level, this does:

1. `bundle:dump` / `feature:dump` (prepare metadata used by JS tooling)
2. run legacy Webpack storefront build plus component/runtime builds (`npm run production`)
3. install bundle assets (`bin/console assets:install`)
4. compile theme

For component-only rebuilds:

```bash
composer npm:storefront run build:components
php bin/console assets:install
php bin/console theme:compile --sync
```

## Dev server flow

Start from project root:

```bash
composer storefront:dev-server
```

The Vite dev server:

- serves component modules and styles from source
- writes `var/cache/storefront_components.dev.json` (dev import map + CSS/JS URLs)
- exposes `/theme-scss/all.css` for theme styles in dev
- serves component style files via `/__sw-comp-css/...`

When the dev server stops, Shopware falls back to production assets/import map.

## File map

### Core Vite config entry points

- `../vite.components.config.mts`
  - main Vite config used by `composer storefront:dev-server`
  - builds component entries and wires all dev/build plugins
- `../vite.shopware.config.mts`
  - builds `src/shopware.ts` into `Resources/public/storefront/shopware/shopware.js`

### Component build orchestration

- `vite/build-components.js`
  - orchestrates component builds across all bundles from `var/plugins.json`
  - per bundle:
    - clears `Resources/app/storefront/dist-es/components` before processing
    - uses custom `Resources/app/storefront/vite.components.config.mts` when present
    - otherwise performs generic inline Vite build
  - enforces one-style-source rule (`Foo.scss` xor `Foo.css`)
  - emits `dist-es/components/.vite/manifest.json` (+ `build-meta.json`)

### Shared component config

- `vite/component-config-factory.ts`
  - reusable config factory for component builds
  - centralizes:
    - entry discovery (`js/ts/scss/css`)
    - output naming
    - resolver/plugins stack
    - style-source collision checks
  - intended for both generic config and extension custom configs

- `vite/vite.components.generic.config.mts`
  - thin env-based adapter around `createComponentBuildConfig()`
  - used by orchestrated generic bundle builds

- `vite/component-entries.ts`
  - helpers to discover component entries in core Storefront
  - builds JS entries and style entries
  - creates virtual entry ids for plain CSS

### Vite plugins

- `vite/component-map-plugin.ts`
  - rewrites entry-chunk vendor imports back to bare specifiers
  - emits `.vite/build-meta.json` (manifest + specifier -> chunk path map)
  - required for PHP runtime import-map aggregation of components and vendor chunks

- `vite/plain-css-shim-plugin.ts`
  - routes plain `.css` entries through Vite CSS pipeline
  - ensures plain CSS gets proper manifest entries (not generic assets)

- `vite/dev-import-map-plugin.ts`
  - dev-only plugin
  - writes `var/cache/storefront_components.dev.json`
  - provides dev import map + styles/scripts URLs
  - serves component styles via `/__sw-comp-css/...`
  - watches component style changes and triggers full reloads

- `vite/theme-scss-watcher-plugin.ts`
  - dev-only plugin for theme SCSS
  - reads `var/theme-files.json`, compiles all theme style entries together
  - serves result at `/theme-scss/all.css`
  - watches SCSS graph and `theme-files.json`

- `vite/dev-server-notice-plugin.ts`
  - dev-only UX plugin
  - replaces default Vite URL output with Shopware-specific usage hint

- `vite/extension-module-resolver-plugin.ts`
  - resolves bare imports from extension component files
  - bridges `Resources/views/components` <-> sibling `Resources/app/storefront/node_modules`
  - used in dev server and component tests

- `vite/scoped-subpath-exports-plugin.ts`
  - workaround for scoped package subpath export resolution
  - resolves imports like `@scope/pkg/subpath` via package `exports`

### Supporting scripts

- `link-component-node-modules.js`
  - creates `Resources/views/components/node_modules` symlink
  - helps IDE/tsc/vitest resolve bare imports outside Vite plugin hooks
  - executed in storefront `postinstall`

- `start-hot-reload.js`
  - deprecated legacy Webpack hot-reload proxy
  - kept for old HMR mode; Vite dev server is the preferred path

- `vite/component-map-plugin.test.ts`
  - unit tests for vendor map plugin behavior

## Extension custom config

Extensions can provide:

`<bundle>/Resources/app/storefront/vite.components.config.mts`

Recommended pattern:

- import and call `createComponentBuildConfig()` from `vite/component-config-factory.ts`
- override only what is needed (for example `sourcemap`, extra plugins, aliases)

This keeps extension configs aligned with Shopware defaults.
