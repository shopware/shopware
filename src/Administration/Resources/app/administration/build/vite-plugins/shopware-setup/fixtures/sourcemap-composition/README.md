# Sourcemap Composition Fixture

A real Vite build of the shipped SFC pipeline (`build/vite-plugins/vue-sfc`): the Shopware setup plugin followed by `@vitejs/plugin-vue`.

The transform's own map is covered by its unit specs, but the builds never ship that map alone. The setup plugin serves the rewritten SFC from `load` together with its map, plugin-vue compiles it and adds its own map, and Rollup chains the two. This fixture proves that chain:

- `vite.config.ts` builds `src/Entry.ts` with the shared pipeline and writes the output.
- `src/sw-nested-component.vue` is a base component whose body stays in place; `src/sw-nested-component.override.vue` is an override whose body the transform relocates.
- `probe.ts` runs the build, reads the written `.js.map`, and reports where a marker in the bundle maps back to.

The spec asserts that both markers map to their authored line, that the map names only the authored files (no virtual `.shopware-setup.vue` ids, no absolute paths), and that it embeds the authored source rather than the transform output.
