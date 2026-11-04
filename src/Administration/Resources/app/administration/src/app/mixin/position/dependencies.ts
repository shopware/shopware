/**
 * @sw-package framework
 *
 * Everything position mixin needs from outside its mini-module.
 */
/** @private */
export { defineComponent } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export type { default as Repository } from 'src/core/data/repository.data';

/** @private */
export type ApiContext = typeof Shopware.Context.api;
