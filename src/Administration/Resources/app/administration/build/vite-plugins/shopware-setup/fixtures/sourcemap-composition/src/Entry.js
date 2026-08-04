/**
 * @sw-package framework
 */

// An SFC import has to carry its `.vue` extension for the bundler to resolve it, but the shared
// `import/extensions` rule is configured `vue: 'never'`. Disabled here so the fixture stays runnable;
// the rule itself needs revisiting now that authors import .vue files.
/* eslint-disable import/extensions */
import Component from './sw-nested-component.vue';
import Override from './sw-nested-component.override.vue';

// A side-effecting reference on purpose: exporting them would let Rollup tree-shake both components out,
// and the emitted map has to cover a base component whose body stays in place as well as an override
// whose body is relocated into a callback.
// eslint-disable-next-line no-console
console.log(Component, Override);
