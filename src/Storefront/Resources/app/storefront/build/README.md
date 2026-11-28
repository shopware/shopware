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

## Production JavaScript SBOMs

Production Vite builds generate CycloneDX 1.6 JSON reports for the JavaScript they emit.
Every bundle receives its own component-build report, covering all of its component entries, bundled vendor chunks, and dynamic imports together.
The shared `shopware.ts` build receives a separate report.
Vite-built dedicated and shared Web Workers, including nested and inline workers, are included in the owning bundle's report.

| Build target | Report path relative to the bundle |
| --- | --- |
| Shared Shopware module | `Resources/sbom/storefront/shopware.cdx.json` |
| Storefront or extension components | `Resources/sbom/storefront/components.cdx.json` |

Run `npm run build:shopware` or `npm run build:components` from the Storefront app directory, or use the full production build.
The component orchestrator automatically adds SBOM generation to custom extension configurations, including configurations that do not use the shared factory.
The shared factory and the core `vite.components.config.mts` also generate reports when built directly in production mode.
Production builds using custom modes, such as `vite build --mode staging`, also generate reports.
Development mode, builds with `NODE_ENV=development`, and the dev server do not generate reports.

Reports describe bundled npm packages and their exact resolved versions, regardless of whether they were installed as development dependencies.
Fully tree-shaken packages and unused installed packages are excluded.
Build tools are recorded separately in metadata.
Root names identify the bundle and build target; a release version is included when the owning Composer project declares it.
Plugins with resources below `src/Resources` use the plugin's Composer metadata above `src`.
The build package's placeholder npm version is not used as the Shopware release version.

File components identify emitted JavaScript files and their SHA-256 hashes.
Worker files and their bundled packages are included alongside the main chunks.
Inline worker packages are associated with the JavaScript file containing the worker code, without listing a separate worker file that was never emitted.
The `shopware:sbom:output-directory` metadata property records the corresponding output directory relative to the bundle root.
Vendor imports rewritten through the import map still count as bundled code.
Externally supplied modules, such as `shopware` in component builds, are listed as `shopware:sbom:external-module` metadata properties and are not counted as bundled npm packages.

Custom output directories do not change the canonical report directory.
Explicit relative `rolldownOptions.output.dir` paths follow the bundler's working directory, including a configured `rolldownOptions.cwd`.
For multiple JavaScript outputs, the first report keeps the standard filename and later reports use `<target>-<output number>-<format>.cdx.json`, for example `components-2-cjs.cdx.json`.
Builds remove previous reports for their target, including when the component orchestrator skips a bundle with no buildable components.
Production watch rebuilds replace these reports and retain stable output names, including after a failed build is corrected.
Reports are persisted after assets are written; generation or persistence failures fail the build.
The files stay under `Resources/sbom/storefront`, outside public assets and build sources.
The `assets:install` command does not publish these reports.
Extension packaging must retain this directory after removing build sources and `node_modules`.
The CLI's packaging/merging of these reports is a separate integration.

### Coverage boundaries

These reports cover Vite-generated JavaScript, not the entire Storefront or extension package.
The production command also runs webpack, whose JavaScript needs separate accounting.
Copied JavaScript, CSS, static assets, and dependencies supplied by external providers are outside the bundled npm inventory.
Workers loaded from external URLs or copied without being built by Vite require separate accounting.
Virtual or generated modules appear in `shopware:sbom:virtual-module` metadata when observed, but the build cannot infer an npm package origin for arbitrary generated code.
Linked packages resolved outside `node_modules` also require separate provenance accounting.
Each report includes these coverage limits in its metadata.

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

Environment overrides:

- `STOREFRONT_VITE_PORT` - changes the Vite port (default `5175`)
- `STOREFRONT_VITE_HOST` - bind host for Vite (default `localhost`)
- `STOREFRONT_VITE_ORIGIN` - absolute URL used in `storefront_components.dev.json`
  (useful in Docker/WSL/remote setups, e.g. `http://host.docker.internal:5175`)
- `SHOPWARE_STOREFRONT_DEV_UPDATE` - how a source change reaches the open page
  - `reload` (default) - the page reloads
  - `hmr` - the change is applied to the open page in place
  - `manual` - nothing is sent to the page, you reload it yourself

`SHOPWARE_STOREFRONT_DEV_UPDATE` is read from the shell first, then from the `.env.local` and `.env` of the project root.
Put it into `.env.local` to keep your choice for every session.

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
    - clears `<bundle>/Resources/public/storefront/components` before processing
    - uses custom `<bundle>/Resources/app/storefront/vite.components.config.mts` when present
    - otherwise performs generic inline Vite build
  - normalizes a trailing `/index` in component entry paths to the component directory name
  - warns when multiple source files produce the same normalized entry name; the later entry replaces the earlier one
  - enforces one-style-source rule (`Foo.scss` xor `Foo.css`)
  - emits component chunks and `.vite/build-meta.json` under `<bundle>/Resources/public/storefront/components/`
  - after `assets:install`, published assets live at `public/bundles/<bundle>/storefront/components/`

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

- `vite/storefront-sbom.mjs`
  - adapts `rollup-plugin-sbom` to retained output modules and explicit per-bundle identities
  - preserves dependency relationships, external-module metadata, and final JavaScript file hashes
  - persists reports in the bundle's canonical `Resources/sbom/storefront` directory

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
  - puts the Vite client (`/@vite/client`) first in `scripts`, because no Storefront module uses `import.meta.hot`
  - leaves the Vite client out in `manual` mode
  - serves component styles via `/__sw-comp-css/...`
  - reloads the page on a style change, or swaps component styles in place in `hmr` mode
  - reloads the page when a style file is added or removed

- `vite/theme-scss-watcher-plugin.ts`
  - dev-only plugin for theme SCSS
  - reads `var/theme-files.json`, compiles all theme style entries together
  - serves result at `/theme-scss/all.css`
  - watches SCSS graph and `theme-files.json`
  - compiles once on a SCSS change, then reloads the page, or swaps `/theme-scss/all.css` in place in `hmr` mode
  - shows the Vite error overlay when the SCSS does not compile
  - reloads the page when `theme-files.json` changes
  - only logs changes in `manual` mode

- `vite/storefront-dev-update.ts`
  - reads `SHOPWARE_STOREFRONT_DEV_UPDATE` and falls back to `reload` for an unset or unknown value

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
