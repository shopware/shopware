interface AdminHelpCenterState {
    showHelpSidebar: boolean;
    showShortcutModal: boolean;
}

/**
 * This file contains the store for the help center.
 *
 * @package buyers-experience
 *
 * @private
 */
export default {
    namespaced: true,

    state(): AdminHelpCenterState {
        return {
            showHelpSidebar: false,
            showShortcutModal: false,
        };
    },

    mutations: {
        setShowHelpSidebar(state: AdminHelpCenterState, showHelpSidebar: boolean) {
            state.showHelpSidebar = showHelpSidebar;
        },

        setShowShortcutModal(state: AdminHelpCenterState, showShortcutModal: boolean) {
            state.showShortcutModal = showShortcutModal;
        },
    },
};

/**
 * @private
 */
export type { AdminHelpCenterState };
