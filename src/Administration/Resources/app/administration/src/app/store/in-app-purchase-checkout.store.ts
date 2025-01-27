/**
 * @sw-package checkout
 */
import type { iapCheckout } from '@shopware-ag/meteor-admin-sdk/es/iap';
import type { Extension } from 'src/app/state/extensions.store';

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

const inAppPurchaseCheckoutStore = Shopware.Store.register({
    id: 'inAppPurchaseCheckout',

    state: (): InAppPurchaseCheckoutState => ({
        entry: null,
        extension: null,
    }),

    actions: {
        // @deprecated tag:v6.7.0 - extension will only be string
        request(entry: InAppPurchaseRequest, extension: Extension | string): void {
            if (Shopware.Utils.types.isObject(extension)) {
                extension = extension.name;
            }

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
