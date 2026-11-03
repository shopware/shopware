/**
 * @sw-package framework
 *
 * Everything sw-license-violation needs from outside its mini-module.
 */
import { licenseViolationStoreId } from 'src/app/store/license-violation.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { cacheApiService, extensionStoreActionService, licenseViolationService, loginService } from 'src/app/service-names';

/** @private */
export const useLicenseViolationStore = () => Shopware.Store.get(licenseViolationStoreId);
