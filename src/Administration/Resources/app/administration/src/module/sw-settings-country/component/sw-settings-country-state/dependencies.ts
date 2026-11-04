/**
 * @sw-package fundamentals@discovery
 *
 * Everything sw-settings-country-state needs from outside its mini-module.
 */
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
