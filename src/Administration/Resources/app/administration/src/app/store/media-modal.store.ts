/**
 * @sw-package framework
 */

import type { uiMediaModalOpen, uiMediaModalOpenSaveMedia } from '@shopware-ag/meteor-admin-sdk/es/ui/media-modal';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type MediaModalConfig = Omit<uiMediaModalOpen, 'responseType'>;
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type SaveMediaModalConfig = Omit<uiMediaModalOpenSaveMedia, 'responseType'>;

const mediaModalStore = Shopware.Store.register({
    id: 'mediaModal',

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
 * @private
 */
export type MediaModalStore = ReturnType<typeof mediaModalStore>;

/**
 * @private
 */
export default mediaModalStore;
