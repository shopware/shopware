/**
 * @sw-package framework
 */

import type { uiModalOpen } from '@shopware-ag/meteor-admin-sdk/es/ui/modal';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type ModalItemEntry = Omit<uiModalOpen, 'responseType'> & {
    baseUrl: string;
};

const modalsStore = Shopware.Store.register({
    id: 'modals',

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
            });
        },

        closeModal(locationId: string): void {
            this.modals = this.modals.filter((modal) => {
                return modal.locationId !== locationId;
            });
        },

        closeLastModalWithoutLocationId(): void {
            let modalsWithoutLocationId = this.modals.filter((modal) => !modal.locationId);
            const modalsWithLocationId = this.modals.filter((modal) => modal.locationId);
            const lastModal = modalsWithoutLocationId[modalsWithoutLocationId.length - 1];

            if (lastModal) {
                modalsWithoutLocationId = modalsWithoutLocationId.filter((modal) => modal !== lastModal);
            }

            this.modals = [...modalsWithoutLocationId, ...modalsWithLocationId];
        },
    },
});

/**
 * @private
 */
export type ModalsStore = ReturnType<typeof modalsStore>;

/**
 * @private
 */
export default modalsStore;
