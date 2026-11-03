/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-config needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export {
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
