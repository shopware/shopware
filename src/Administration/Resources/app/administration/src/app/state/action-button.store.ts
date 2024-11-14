/**
 * @package admin
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */

import type { Module } from 'vuex';
import type { actionButtonAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/action-button';

type ActionButtonConfig = Omit<actionButtonAdd, 'responseType'>;

interface ActionButtonState {
    buttons: Array<ActionButtonConfig>;
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
 * @private
 */
export default ActionButtonStore;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type { ActionButtonState, ActionButtonConfig };
