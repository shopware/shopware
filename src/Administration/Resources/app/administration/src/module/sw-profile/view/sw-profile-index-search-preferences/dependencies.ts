/**
 * @sw-package fundamentals@framework
 *
 * Everything sw-profile-index-search-preferences needs from outside its mini-module.
 */
import { swProfileStoreId } from '../../store/sw-profile.store';

/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { searchPreferencesService, searchRankingService } from 'src/app/service-names';

/** @private */
export const useProfileStore = () => Shopware.Store.get(swProfileStoreId);

/** @private */
export const getAppContext = () => Shopware.Context.app;
