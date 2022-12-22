/**
 * @package admin
 */

const { hasOwnProperty } = Shopware.Utils.object;

/**
 * @package system-settings
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    namespaced: true,
    state: {
        settingsGroups: {
            shop: [],
            system: [],
            plugins: [],
        },
    },

    mutations: {
        addItem(state, settingsItem) {
            const group = settingsItem.group;

            if (!hasOwnProperty(state.settingsGroups, group)) {
                state.settingsGroups[group] = [];
            }

            if (state.settingsGroups[group].some((setting) => setting.name === settingsItem.name)) {
                return;
            }

            state.settingsGroups[group].push(settingsItem);
        },
    },
};
