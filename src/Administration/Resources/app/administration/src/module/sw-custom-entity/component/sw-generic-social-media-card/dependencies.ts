/**
 * @sw-package discovery
 *
 * Everything sw-generic-social-media-card needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { default as Repository } from 'src/core/data/repository.data';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
