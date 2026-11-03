/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-detail-analytics needs from outside its mini-module.
 */
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
