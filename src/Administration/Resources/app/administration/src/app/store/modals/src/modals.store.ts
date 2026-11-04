/**
 * @sw-package framework
 */
import { defineStoreOptions, getStore, type StoreOf, type uiModalOpen } from '../dependencies';

/**
 * @public
 */
export type ModalItemEntry = Omit<uiModalOpen, 'responseType' | 'variant' | 'zIndex'> & {
    baseUrl: string;
    // Keep these fields local so Admin can support newer Meteor messages while
    // still compiling against older Meteor SDK versions.
    variant?: 'default' | 'small' | 'large' | 'x-large' | 'full';
    zIndex?: number;
};

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const modalsStoreId = 'modals';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const modalsStoreOptions = defineStoreOptions({
    id: modalsStoreId,

    state: () => ({
        modals: [] as ModalItemEntry[],
    }),

    actions: {
        openModal({
            locationId,
            title,
            closable,
            showHeader,
            showFooter,
            variant,
            baseUrl,
            buttons,
            textContent,
            zIndex,
        }: ModalItemEntry) {
            this.modals.push({
                title,
                closable,
                showHeader,
                showFooter,
                variant,
                locationId,
                buttons: buttons ?? [],
                baseUrl,
                textContent,
                ...(zIndex !== undefined ? { zIndex } : {}),
            });
        },

        closeModal(locationId: string): void {
            this.modals = this.modals.filter((modal) => {
                return modal.locationId !== locationId;
            });
        },

        closeLastModalWithoutLocationId(): void {
            const lastModalWithoutLocationId = this.modals.filter((modal) => !modal.locationId).at(-1);

            if (lastModalWithoutLocationId) {
                this.modals = this.modals.filter((modal) => modal !== lastModalWithoutLocationId);
            }
        },
    },
});

/**
 * @public
 */
export type ModalsStore = StoreOf<typeof modalsStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useModalsStore = (): ModalsStore => getStore(modalsStoreId);
