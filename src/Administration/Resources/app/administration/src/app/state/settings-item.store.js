const { hasOwnProperty } = Shopware.Utils.object;

export default {
    namespaced: true,
    state: {
        settingsGroups: {},
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
