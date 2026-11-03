/**
 * @sw-package discovery
 *
 * Everything sw-model-viewer needs from outside its mini-module.
 */
/** @private */
export { QuickView } from '@shopware-ag/dive/quickview';
/** @private */
export { defineComponent as wrapComponentConfig, markRaw } from 'vue';
/** @private */
export type { Raw } from 'vue';
/** @private */
export type { default as Repository } from 'src/core/data/repository.data';
/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
