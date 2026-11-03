/**
 * @sw-package framework
 *
 * Everything sw-sso-users-permission-user-detail needs from outside its mini-module.
 */
import { timezoneService } from 'src/app/service-names';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as useTheme } from 'src/app/composables/use-theme';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as salutationMixin } from 'src/app/mixin/salutation.mixin';
/** @private */
export {
    acl as aclService,
    integrationService,
    repositoryFactory as repositoryFactoryService,
    userService,
} from 'src/app/service-names';

/** @private */
export const getTimezoneService = (): ServiceContainer['timezoneService'] => Shopware.Service(timezoneService);

/** @private */
export const getApiContext = () => Shopware.Context.api;
