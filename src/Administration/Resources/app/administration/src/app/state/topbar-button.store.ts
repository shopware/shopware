/**
 * @package customer-order
 * @private
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type TopBarButtonState = {
    state: {
        buttons: unknown[],
    },
    actions: unknown,
    getters: unknown,
};

const TopBarButtonStore = {
    id: 'topBarButtonState',

    state: (): TopBarButtonState['state'] => ({
        buttons: [],
    }),
};

/**
 * @private
 */
export default TopBarButtonStore;
