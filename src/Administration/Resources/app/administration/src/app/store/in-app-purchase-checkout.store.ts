/**
 * @sw-package checkout
 */
import type { iapCheckout } from '@shopware-ag/meteor-admin-sdk/es/iap';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type InAppPurchaseRequest = Omit<iapCheckout, 'responseType'>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type InAppPurchaseCheckoutState =
    | {
          entry: null;
          extension: null;
      }
    | {
          entry: InAppPurchaseRequest;
          extension: string;
      };

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const inAppPurchaseCheckoutStore = Shopware.Store.register({
    id: 'inAppPurchaseCheckout',

    state: (): InAppPurchaseCheckoutState => ({
        entry: null,
        extension: null,
    }),

    actions: {
        request(entry: InAppPurchaseRequest, extension: string): void {
            if (!Shopware.Context.app.config.bundles?.[extension]) {
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
 * @private
 */
export type InAppPurchasesStore = ReturnType<typeof inAppPurchaseCheckoutStore>;
