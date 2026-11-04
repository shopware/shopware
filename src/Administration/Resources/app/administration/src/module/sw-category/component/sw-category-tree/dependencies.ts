/**
 * @sw-package discovery
 *
 * Everything sw-category-tree needs from outside its mini-module.
 */

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { repositoryFactory as repositoryFactoryService, syncService } from 'src/app/service-names';

/** @private */
export { useSwCategoryDetailStore as useCategoryDetailStore } from '../../page/sw-category-detail/store';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const debounce = ((...args: unknown[]) =>
    (Shopware.Utils.debounce as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debounce;
