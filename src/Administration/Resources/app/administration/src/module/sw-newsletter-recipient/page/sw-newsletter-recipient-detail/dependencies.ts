/**
 * @sw-package after-sales
 *
 * Everything sw-newsletter-recipient-detail needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const publishData = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.publishData as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.ExtensionAPI.publishData;
