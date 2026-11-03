/**
 * @sw-package checkout
 *
 * Everything sw-customer-card needs from outside its mini-module.
 */
import { decodeIdnEmailFilterName } from 'src/app/filter/decode-idn-email.filter';
import { errorStoreId } from 'src/app/store/error.store';
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as salutationMixin } from 'src/app/mixin/salutation.mixin';
/** @private */
export {
    acl as aclService,
    contextStoreService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as ApiService } from '../../../../core/service/api.service';
/** @private */
export { default as CUSTOMER } from '../../constant/sw-customer.constant';
/** @private */
export { default as errorConfig } from '../../error-config.json';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getDecodeIdnEmailFilter = () => Shopware.Filter.getByName(decodeIdnEmailFilterName);
