/**
 * @sw-package framework
 *
 * Everything sw-custom-field-set-detail-base needs from outside its mini-module.
 */
import { repositoryFactory } from 'src/app/service-names';

/** @private */
export { acl as aclService, customFieldDataProviderService } from 'src/app/service-names';

/** @private */
export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);
