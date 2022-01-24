import type { Module } from 'vuex';
import { actionButtonAdd } from '@shopware-ag/admin-extension-sdk/es/ui/actionButton';

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

export default ActionButtonStore;
export type { ActionButtonState, ActionButtonConfig };
