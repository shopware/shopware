export default {
    namespaced: true,

    state() {
        return {
            currentLocale: 'en-GB'
        };
    },

    getters: {
    },

    mutations: {
        setCurrentLocale(state, value) {
            state.currentLocale = value;
        }
    }
};
