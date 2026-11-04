/**
 * @sw-package framework
 */
import { type actionButtonAdd, defineStoreOptions, getStore, type StoreOf } from '../dependencies';

type ActionButtonConfig = Omit<actionButtonAdd, 'responseType'>;

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const actionButtonsStoreId = 'actionButtons';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const actionButtonsStoreOptions = defineStoreOptions({
    id: actionButtonsStoreId,

    state: () => ({
        /**
         * List of all action buttons
         */
        buttons: [] as ActionButtonConfig[],
    }),

    actions: {
        /**
         * Add a new action button
         * @param button - The button to add
         */
        add(button: ActionButtonConfig): void {
            this.buttons.push(button);
        },
    },
});

/**
 * @public
 */
export type ActionButtonsStore = StoreOf<typeof actionButtonsStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useActionButtonsStore = (): ActionButtonsStore => getStore(actionButtonsStoreId);
