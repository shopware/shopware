# Dev Server Error Location Fixture

This fixture tests where the Vite dev server reports a transform error of a Shopware setup SFC that is imported by another module.

The browser never requests the SFC first. It requests the importer, and Vite's import analysis of the importer resolves the SFC. The setup plugin transforms the SFC in `resolveId`, so a transform error is thrown inside the importer's transform. Vite then traces the error's `loc` through the importer's sourcemap. When the importer's code has a mapping at the error's line, Vite rewrites `loc.file` to the importer, and the terminal and the error overlay point at the wrong file.

This fixture keeps that case visible:

- `src/sw-comp.vue` has a parse error at line 9.
- `src/Entry.ts` imports it and is longer than 9 lines after compilation, so its sourcemap can map line 9.
- `probe.ts` starts a dev server with the shipped plugin and `@vitejs/plugin-vue`, requests `/src/Entry.ts` and prints the reported error.
- The spec checks that the error still points at `src/sw-comp.vue`, line 9.

Unit specs cannot catch this, because only a real dev server applies the importer's sourcemap.
