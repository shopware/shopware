/**
 * @sw-package checkout
 */
import { defineStoreOptions, getAppContext, getStore, type iapCheckout, type StoreOf } from '../dependencies';

/**
 * @public
 */
export type InAppPurchaseRequest = Omit<iapCheckout, 'responseType'>;

/**
 * @public
 */
export type InAppPurchaseCheckoutState =
    | {
          entry: null;
          extension: null;
      }
    | {
          entry: InAppPurchaseRequest;
          extension: string;
      };

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const inAppPurchaseCheckoutStoreId = 'inAppPurchaseCheckout';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const inAppPurchaseCheckoutStoreOptions = defineStoreOptions({
    id: inAppPurchaseCheckoutStoreId,

    state: (): InAppPurchaseCheckoutState => ({
        entry: null,
        extension: null,
    }),

    actions: {
        request(entry: InAppPurchaseRequest, extension: string): void {
            if (!getAppContext().config.bundles?.[extension]) {
                throw new Error(`Extension with the name "${extension}" not found.`);
            }

            this.entry = entry;
            this.extension = extension;
        },

        dismiss(): void {
            this.entry = null;
            this.extension = null;
        },
    },
});

/**
 * @public
 */
export type InAppPurchasesStore = StoreOf<typeof inAppPurchaseCheckoutStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useInAppPurchaseCheckoutStore = (): InAppPurchasesStore => getStore(inAppPurchaseCheckoutStoreId);
