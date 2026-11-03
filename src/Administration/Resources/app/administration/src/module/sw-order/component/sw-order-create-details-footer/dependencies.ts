/**
 * @sw-package checkout
 *
 * Everything sw-order-create-details-footer needs from outside its mini-module.
 */
import { repositoryFactory } from 'src/app/service-names';
import { swOrderStoreId } from '../../store/order.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';

/** @private */
export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);

/** @private */
export const useOrderStore = () => Shopware.Store.get(swOrderStoreId);
