const { Application } = Shopware;

export default {
    namespaced: true,
    state: {
        mode: ''
    },

    getters: {
        getMode(state) {
            const colorSchemeService = Application.getContainer('service').preferedColorSchemeService;
            return state.mode || colorSchemeService.mode;
        }
    },

    actions: {
        setMode({commit}, mode) {
            commit('setMode', { mode });
        }
    },

    mutations: {
        setMode(state, { mode }) {
            const colorSchemeService = Application.getContainer('service').preferedColorSchemeService;
            colorSchemeService.mode = state.mode = mode;
            colorSchemeService.store();
        }
    }
};
