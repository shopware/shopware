/**
 * @sw-package discovery
 *
 * Everything sw-cms-create needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';
import { cmsPageStoreId } from '../../store/cms-page.store';

/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
