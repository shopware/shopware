/**
 * @sw-package inventory
 *
 * Everything sw-settings-listing-option-create needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);
