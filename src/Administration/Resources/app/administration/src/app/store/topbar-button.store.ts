/**
 * @package customer-order
 * @private
 * @description Apply for upselling service only, no public usage
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type TopBarButtonState = {
    state: {
        buttons: unknown[],
    },
    actions: unknown,
    getters: unknown,
};

const TopBarButtonStore = Shopware.Store.wrapStoreDefinition({
    id: 'topBarButtonState',

    state: (): TopBarButtonState['state'] => ({
        buttons: [],
    }),
});

/**
 * @private
 */
export default TopBarButtonStore;
