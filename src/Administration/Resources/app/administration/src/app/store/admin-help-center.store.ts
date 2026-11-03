/**
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const adminHelpCenterStoreId = 'adminHelpCenter';

/**
 * @sw-package framework
 */

const adminHelpCenterStore = Shopware.Store.register({
    id: adminHelpCenterStoreId,

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
