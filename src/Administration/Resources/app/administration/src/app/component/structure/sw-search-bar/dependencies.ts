/**
 * @sw-package framework
 *
 * Everything sw-search-bar needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { default as useModuleIconColors } from 'src/app/composables/use-module-icon-colors';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    recentlySearchService,
    repositoryFactory as repositoryFactoryService,
    searchRankingService,
    searchService,
    searchTypeService,
    userActivityApiService,
} from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;
