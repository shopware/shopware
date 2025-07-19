/**
 * @sw-package framework
 */

const adminHelpCenterStore = Shopware.Store.register({
    id: 'adminHelpCenter',

    state: () => {
        return {
            showHelpSidebar: false,
            showShortcutModal: false,
        };
    },
});

/**
 * @private
 */
export type AdminHelpCenterStore = ReturnType<typeof adminHelpCenterStore>;

/**
 * @private
 */
export default adminHelpCenterStore;
