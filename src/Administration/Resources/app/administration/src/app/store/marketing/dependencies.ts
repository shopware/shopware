/**
 * @sw-package framework
 *
 * Everything marketing store needs from outside its mini-module.
 */
import { shopwareDiscountCampaignService } from 'src/app/service-names';

/** @private */
export type { DiscountCampaign } from 'src/module/sw-extension/service/extension-store-action.service';

/** @private */
export const getShopwareDiscountCampaignService = () => Shopware.Service(shopwareDiscountCampaignService);

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getSnippet = () => Shopware.Snippet;

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
