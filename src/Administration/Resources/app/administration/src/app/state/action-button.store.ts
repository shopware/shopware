/**
 * @package admin
 */

import type { Module } from 'vuex';
import type { actionButtonAdd } from '@shopware-ag/admin-extension-sdk/es/ui/actionButton';

type ActionButtonConfig = Omit<actionButtonAdd, 'responseType'>

interface ActionButtonState {
    buttons: Array<ActionButtonConfig>,
}

const ActionButtonStore: Module<ActionButtonState, VuexRootState> = {
    namespaced: true,

    state: (): ActionButtonState => ({
        buttons: [],
    }),

    mutations: {
        add(state, button: ActionButtonConfig) {
            state.buttons.push(button);
        },
    },
};

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default ActionButtonStore;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { ActionButtonState, ActionButtonConfig };
