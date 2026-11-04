/**
 * @sw-package framework
 */
import {
    defineStoreOptions,
    getStore,
    type StoreOf,
    type uiMediaModalOpen,
    type uiMediaModalOpenSaveMedia,
} from '../dependencies';

/**
 * @public
 */
export type MediaModalConfig = Omit<uiMediaModalOpen, 'responseType'>;
/**
 * @public
 */
export type SaveMediaModalConfig = Omit<uiMediaModalOpenSaveMedia, 'responseType'>;

/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const mediaModalStoreId = 'mediaModal';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const mediaModalStoreOptions = defineStoreOptions({
    id: mediaModalStoreId,

    state: () => ({
        mediaModal: null as MediaModalConfig | null,
        saveMediaModal: null as SaveMediaModalConfig | null,
    }),

    actions: {
        openModal(modalConfig: MediaModalConfig): void {
            this.mediaModal = modalConfig;
        },

        closeModal(): void {
            this.mediaModal = null;
        },

        closeSaveModal(): void {
            this.saveMediaModal = null;
        },

        openSaveModal(modalConfig: SaveMediaModalConfig): void {
            this.saveMediaModal = modalConfig;
        },
    },
});

/**
 * @public
 */
export type MediaModalStore = StoreOf<typeof mediaModalStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useMediaModalStore = (): MediaModalStore => getStore(mediaModalStoreId);
