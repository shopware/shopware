export default {
    namespaced: true,

    state() {
        return {
            apps: [],
            selectedIds: [],
        };
    },

    getters: {
        /** @deprecated tag:v6.5.0 use adminMenu.appModuleNavigation instead */
        navigation(state, getters, rootState, rootGetters) {
            return rootGetters['adminMenu/appModuleNavigation'];
        },
    },

    mutations: {
        setApps(state, apps) {
            state.apps = apps;
        },

        setSelectedIds(state, selectedIds) {
            state.selectedIds = selectedIds;
        },
    },

    actions: {
        setAppModules({ commit }, modules) {
            commit('setApps', modules);
        },

        setSelectedIds({ commit }, selectedIds) {
            commit('setSelectedIds', selectedIds);
        },
    },
};
