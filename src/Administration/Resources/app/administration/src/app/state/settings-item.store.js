const { hasOwnProperty } = Shopware.Utils.object;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 */
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
