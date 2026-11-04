/**
 * @sw-package framework
 *
 * Everything sw-custom-field-set-renderer needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export { computed } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { mapInheritanceSlotPropsToMeteorProps } from 'src/core/service/utils/meteor-inheritance.utils';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { default as swInlineSnippetMixin } from 'src/app/mixin/sw-inline-snippet.mixin';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;
