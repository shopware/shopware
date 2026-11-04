/**
 * @sw-package discovery
 *
 * Everything sw-settings-delivery-time-detail needs from outside its mini-module.
 */
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const getApiContext = () => Shopware.Context.api;
