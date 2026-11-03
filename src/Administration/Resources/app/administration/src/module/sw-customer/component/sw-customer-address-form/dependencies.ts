/**
 * @sw-package checkout
 *
 * Everything sw-customer-address-form needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { default as EntityValidationService } from 'src/app/service/entity-validation.service';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as CUSTOMER } from '../../constant/sw-customer.constant';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);
