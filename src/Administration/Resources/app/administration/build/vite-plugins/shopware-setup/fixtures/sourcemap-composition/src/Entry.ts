/**
 * @sw-package framework
 */
import Component from './sw-nested-component.vue';
import Override from './sw-nested-component.override.vue';

// A side-effecting reference on purpose: exporting them would let Rollup tree-shake both components out,
// and the emitted map has to cover a base component whose body stays in place as well as an override
// whose body is relocated into a callback.
// eslint-disable-next-line no-console
console.log(Component, Override);
