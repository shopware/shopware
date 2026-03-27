# Storefront Component Build Process & Dev Server

## Summary
This PR introduces a lightweight **Vite-based build process** for Storefront component scripts (`.js` / `.ts`) and a **development server** with hot module replacement, enabling third-party npm dependencies and TypeScript in component files.

### Vite build for component scripts
- Each component script is compiled individually (glob multi-entry) into `dist/components/`, mirroring the source structure.
- TypeScript is supported out of the box — developers choose JS or TS per file.
- Third-party dependencies are extracted as shared vendor chunks and resolved at runtime via the existing import map, keeping bundle sizes small.
- A custom Vite plugin rewrites vendor imports back to bare specifiers after bundling so the import map can resolve them correctly.

### Vite dev server
- A dedicated Vite dev server serves component scripts directly from source with HMR — no full build required during development.
- On startup the server writes `var/cache/storefront_components.dev.json`, which is a complete import map replacement pointing all component and `shopware` specifiers to the local dev server.
- On shutdown (clean or crash) the file is removed automatically, restoring the production import map.
- The PHP side (`TemplateConfigAccessor`) detects the file in `dev` environment and returns it verbatim instead of the compiled import map.
- File I/O uses the `shopware.filesystem.temp` Flysystem abstraction, consistent with Shopware conventions.

### Import map in runtime config
The compiled component import map is now stored in the theme **runtime config** rather than being generated inline on every request. `ThemeCompiler` writes the import map into the runtime config record at compile time, and `TemplateConfigAccessor` reads it back at render time — the same pattern already used for theme variables and assets.

### Vitest for component unit tests
- Vitest is configured to discover and run tests from both the core Storefront and any active Shopware extensions.
- A shared `extensionModuleResolverPlugin` ensures npm dependencies installed inside extension `node_modules` directories are resolved correctly in both the test runner and the dev server.

## Composer commands

| Command | Description |
|---|---|
| `composer storefront:components:build` | Production build — run before `theme:compile` |
| `composer storefront:components:dev-server` | Start the Vite dev server on port **5175** (override with `STOREFRONT_COMPONENTS_VITE_PORT`) |
| `composer storefront:components:test` | Run component unit tests with Vitest |
