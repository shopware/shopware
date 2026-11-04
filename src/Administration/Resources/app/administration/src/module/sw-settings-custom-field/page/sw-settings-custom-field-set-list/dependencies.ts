/**
 * @sw-package framework
 *
 * Everything sw-settings-custom-field-set-list needs from outside its mini-module.
 */
import { cacheService } from 'src/app/service-names';

/** @private */
export { acl as aclService, feature as featureService } from 'src/app/service-names';

/** @private */
export const getCacheService = () => Shopware.Service(cacheService);
