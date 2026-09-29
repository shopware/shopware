# Sourcemap Composition Fixture

This fixture intentionally tests a Vite build that runs the Shopware setup pre-transform together with `@vitejs/plugin-vue`.

The shared transform has focused unit coverage for the sourcemap it returns directly. That is not enough for editor and browser debugging, because real Administration builds do not consume that map in isolation. Vite first receives the Shopware setup map, then Vue's SFC plugin compiles the transformed `.vue` file and composes another map on top.

This fixture keeps that integration visible:

- `vite.config.ts` wires the same pre-transform order used by the Administration plugin pipeline.
- `src/sw-nested-component.vue` contains authored `<script setup>` code whose generated output is moved by the Shopware setup transform and then compiled by Vue.
- `probe.ts` runs the fixture build and reports the generated bundle position together with its final composed original source position.
- The spec checks that a position in the final bundled code still maps back to the original `.vue` source line and column.

The value is catching bugs where our transform-level map looks correct, but composition through Vue shifts, drops, or misattributes source positions.

## What this fixture does _not_ cover

It cannot catch a leak in the **emitted map asset**. `generateBundle` remaps `chunk.map`, and in a small isolated build like this one that object is also what Rollup writes to the `.js.map` file — so the assertion passes whether or not the fix that writes the remapped map back to `bundle['<chunk>.js.map']` is present. Verified by removing that write: this fixture stays green.

In the full extension pipeline the `.js.map` asset already exists at `generateBundle` time and is written from the asset, not from `chunk.map`. That is where virtual `*.vue.shopware-setup.vue` filenames leaked into shipped sourcemaps, and only a real `composer build:js:admin` detects it. Reproducing it here would mean building the fixture from the real extension plugin list in `build/plugins.vite.ts`; that was considered and deliberately not done, because the failure mode is degraded debugging rather than broken behaviour.

So: green here means composition through Vue is intact. It does not mean the shipped map is clean.
