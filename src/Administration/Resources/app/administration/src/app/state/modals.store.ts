/**
 * @package admin
 */

import type { Module } from 'vuex';
import type { uiModalOpen } from '@shopware-ag/admin-extension-sdk/es/ui/modal';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type ModalItemEntry = Omit<uiModalOpen, 'responseType'> & { baseUrl: string };

interface ModalsState {
    modals: ModalItemEntry[]
}

const ModalsStore: Module<ModalsState, VuexRootState> = {
    namespaced: true,

    state: (): ModalsState => ({
        modals: [],
    }),

    mutations: {
        openModal(state, {
            locationId,
            title,
            closable,
            showHeader,
            variant,
            baseUrl,
            buttons,
        }: ModalItemEntry) {
            state.modals.push({
                title,
                closable,
                showHeader,
                variant,
                locationId,
                buttons: buttons ?? [],
                baseUrl,
            });
        },

        closeModal(state, locationId: string): void {
            state.modals = state.modals.filter(modal => {
                return modal.locationId !== locationId;
            });
        },
    },
};

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default ModalsStore;
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { ModalsState };
