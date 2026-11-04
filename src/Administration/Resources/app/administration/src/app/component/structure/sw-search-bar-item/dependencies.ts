/**
 * @sw-package framework
 *
 * Everything sw-search-bar-item needs from outside its mini-module.
 */
import { mediaNameFilterName } from 'src/app/filter/media-name.filter';
import { sessionStoreId } from 'src/app/store/session';

/** @private */
export { default as useModuleIconColors } from 'src/app/composables/use-module-icon-colors';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getMediaNameFilter = () => Shopware.Filter.getByName(mediaNameFilterName);
