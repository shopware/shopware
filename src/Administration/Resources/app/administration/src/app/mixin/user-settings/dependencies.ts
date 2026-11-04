/**
 * @sw-package framework
 *
 * Everything user-settings mixin needs from outside its mini-module.
 */
import { userConfigService } from 'src/app/service-names';

/** @private */
export { defineComponent } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { acl as aclService } from 'src/app/service-names';
/** @private */
export { useSessionStore } from 'src/app/store/session';

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);

/** @private */
export const getApiContext = () => Shopware.Context.api;
