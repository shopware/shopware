/**
 * @sw-package discovery
 *
 * Everything sw-media-save-modal needs from outside its mini-module.
 */
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
