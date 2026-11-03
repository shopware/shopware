/**
 * @sw-package framework
 *
 * Everything sw-maintain-currencies-modal needs from outside its mini-module.
 */
import { repositoryFactory } from 'src/app/service-names';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';

/** @private */
export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const cloneDeep = (...args: Parameters<typeof Shopware.Utils.object.cloneDeep>) =>
    Shopware.Utils.object.cloneDeep(...args);
