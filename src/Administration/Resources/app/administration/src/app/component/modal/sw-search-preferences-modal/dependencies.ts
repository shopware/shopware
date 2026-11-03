/**
 * @sw-package framework
 *
 * Everything sw-search-preferences-modal needs from outside its mini-module.
 */
import { userConfigService } from 'src/app/service-names';

/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { searchPreferencesService, searchRankingService } from 'src/app/service-names';
/** @private */
export { KEY_USER_SEARCH_PREFERENCE } from 'src/app/service/search-ranking.service';

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);
