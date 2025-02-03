const { hasOwnProperty } = Shopware.Utils.object;

/**
 * @sw-package framework
 * @private
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */
export default {
    namespaced: true,
    state: {
        settingsGroups: {
            general: [],
            customer: [],
            automation: [],
            localization: [],
            content: [],
            commerce: [],
            system: [],
            account: [],
            plugins: [],
            shop: [],
        },
    },

    mutations: {
        addItem(state, settingsItem) {
            let group = settingsItem.group;

            if (typeof group === 'function') {
                group = group();
            }

            if (!group || typeof group !== 'string') {
                throw new Error('Group is undefined or invalid');
            }

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
