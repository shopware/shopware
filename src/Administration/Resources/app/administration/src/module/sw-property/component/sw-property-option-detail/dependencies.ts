/**
 * @sw-package inventory
 *
 * Everything sw-property-option-detail needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error';

/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);
