/**
 * @sw-package framework
 */

import type { uiModalOpen } from '@shopware-ag/meteor-admin-sdk/es/ui/modal';
import { reactive } from 'vue';
import { useExtensionOrderedArray } from '../composables/use-extension-ordered-container';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type ModalItemEntry = Omit<uiModalOpen, 'responseType'> & {
    baseUrl: string;
};

const modalsStore = Shopware.Store.register('modals', () => {
    const modalsOrdered = useExtensionOrderedArray<ModalItemEntry>();
    const modals = modalsOrdered.items;

    const openModal = ({
        locationId,
        title,
        closable,
        showHeader,
        showFooter,
        variant,
        baseUrl,
        buttons,
        textContent,
    }: ModalItemEntry) => {
        modalsOrdered.push({
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
    };

    const closeModal = (locationId: string): void => {
        modalsOrdered.removeFirstWhere((modal) => modal.locationId === locationId);
    };

    const closeLastModalWithoutLocationId = (): void => {
        const modals = modalsOrdered.items.value;
        const lastModalWithoutLocationId = modals.filter((modal) => !modal.locationId).at(-1);

        if (lastModalWithoutLocationId) {
            modalsOrdered.removeFirstWhere((modal) => modal === lastModalWithoutLocationId);
        }
    };

    return reactive({
        modals,
        openModal,
        closeModal,
        closeLastModalWithoutLocationId,
    });
});

/**
 * @private
 */
export type ModalsStore = ReturnType<typeof modalsStore>;

/**
 * @private
 */
export default modalsStore;
