/**
 * @sw-package framework
 *
 * Everything sw-address needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { RouteLocationNamedRaw } from 'vue-router';
/** @private */
export type { Address } from 'src/core/service/api/custom-snippet.api.service';
