/**
 * @sw-package inventory
 *
 * Everything sw-settings-number-range-detail needs from outside its mini-module.
 */
import { cacheService } from 'src/app/service-names';

/** @private */
export { debug } from 'src/core/service/util.service';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    documentV2ApiService,
    numberRangeService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const getCacheService = () => Shopware.Service(cacheService);

/** @private */
export const getApiContext = () => Shopware.Context.api;
