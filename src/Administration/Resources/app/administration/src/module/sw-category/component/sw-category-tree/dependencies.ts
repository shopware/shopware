/**
 * @sw-package discovery
 *
 * Everything sw-category-tree needs from outside its mini-module.
 */
import { swCategoryDetailStoreId } from '../../page/sw-category-detail/store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { repositoryFactory as repositoryFactoryService, syncService } from 'src/app/service-names';

/** @private */
export const useCategoryDetailStore = () => Shopware.Store.get(swCategoryDetailStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const debounce = ((...args: unknown[]) =>
    (Shopware.Utils.debounce as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debounce;
