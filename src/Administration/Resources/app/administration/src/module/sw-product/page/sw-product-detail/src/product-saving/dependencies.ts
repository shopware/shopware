/**
 * @sw-package inventory
 *
 * Everything product-saving needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error';
import { swSeoUrlStoreId } from 'src/module/sw-settings-seo/component/sw-seo-url/store';

/** @private */
export { types } from 'src/core/service/util.service';
/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { default as EntityValidationService } from 'src/app/service/entity-validation.service';
/** @private */
export { useSwProductDetailStore as useProductDetailStore } from 'src/module/sw-product/page/sw-product-detail/store';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const useSeoUrlStore = () => Shopware.Store.get(swSeoUrlStoreId);

/** Whether the store of the SEO URL card is registered, which happens only once the card has loaded. @private */
export const isSeoUrlStoreRegistered = () => Shopware.Store.list().includes(swSeoUrlStoreId);

/** @private */
export const trackTelemetry = (...args: Parameters<typeof Shopware.Telemetry.track>) => Shopware.Telemetry.track(...args);
