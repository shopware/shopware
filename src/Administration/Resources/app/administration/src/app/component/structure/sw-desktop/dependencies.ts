/**
 * @sw-package framework
 *
 * Everything sw-desktop needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context.store';
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { default as useTheme, THEME_LABELS, THEMES } from 'src/app/composables/use-theme';
/** @private */
export { shopIdChangeService, snackbarService, userActivityApiService } from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const hasOwnProperty = ((...args: unknown[]) =>
    (Shopware.Utils.object.hasOwnProperty as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.object.hasOwnProperty;
