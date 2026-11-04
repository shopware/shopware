/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-defaults-select needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error';

/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const kebabCase = ((...args: unknown[]) =>
    (Shopware.Utils.string.kebabCase as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.string.kebabCase;
