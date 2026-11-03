/**
 * @sw-package inventory
 *
 * Everything sw-settings-units-detail needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from '../../../../app/service/map-errors.service';
/** @private */
export type { default as Repository } from '../../../../core/data/repository.data';

/** @private */
export const getApiContext = () => Shopware.Context.api;
