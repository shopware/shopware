/**
 * @sw-package discovery
 *
 * Everything sw-media-field needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const createId = ((...args: unknown[]) =>
    (Shopware.Utils.createId as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.createId;
