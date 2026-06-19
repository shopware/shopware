# Sourcemap Composition Fixture

This fixture intentionally tests a Vite build that runs the Shopware setup pre-transform together with `@vitejs/plugin-vue`.

The shared transform has focused unit coverage for the sourcemap it returns directly. That is not enough for editor and browser debugging, because real Administration builds do not consume that map in isolation. Vite first receives the Shopware setup map, then Vue's SFC plugin compiles the transformed `.vue` file and composes another map on top.

This fixture keeps that integration visible:

- `vite.config.js` wires the same pre-transform order used by the Administration plugin pipeline.
- `src/NestedComponent.vue` contains authored `<script setup>` code whose generated output is moved by the Shopware setup transform and then compiled by Vue.
- `probe.js` runs the fixture build and reports the generated bundle position together with its final composed original source position.
- The spec checks that a position in the final bundled code still maps back to the original `.vue` source line and column.

The value is catching bugs where our transform-level map looks correct, but composition through Vue shifts, drops, or misattributes source positions.
