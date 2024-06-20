/**
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    namespaced: true,

    state() {
        return {
            config: null,
        };
    },

    mutations: {
        setConfig(state, config) {
            state.config = config;
        },
    },

    getters: {
        getConfig(state) {
            return () => {
                return state.config;
            };
        },

        getConfigForType(state) {
            return (conditionType) => {
                if (!state.config[conditionType]) {
                    return null;
                }

                return state.config[conditionType];
            };
        },
    },
};
