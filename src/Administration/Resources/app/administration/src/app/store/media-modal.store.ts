/**
 * @sw-package framework
 */

import type { uiMediaModalOpen } from '@shopware-ag/meteor-admin-sdk/es/ui/media-modal';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type MediaModalConfig = Omit<uiMediaModalOpen, 'responseType'>;

const mediaModalStore = Shopware.Store.register({
    id: 'mediaModal',

    state: () => ({
        mediaModal: null as MediaModalConfig | null,
    }),

    actions: {
        openModal(modalConfig: MediaModalConfig): void {
            this.mediaModal = modalConfig;
        },

        closeModal(): void {
            this.mediaModal = null;
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
