/**
 * @sw-package inventory
 *
 * Everything sw-seo-main-category needs from outside its mini-module.
 */
import { swSeoUrlStoreId } from '../sw-seo-url/store';

/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useSeoUrlStore = () => Shopware.Store.get(swSeoUrlStoreId);
