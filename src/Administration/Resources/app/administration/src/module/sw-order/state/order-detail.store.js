export default {
    namespaced: true,

    state() {
        return {
            order: null,
            loading: {
                order: false,
            },
            editing: true,
            savedSuccessful: false,
            versionContext: null,
        };
    },

    getters: {
        isLoading: (state) => {
            return Object.values(state.loading).some((loadState) => loadState);
        },

        isEditing: (state) => {
            return state.editing;
        },
    },

    mutations: {
        setOrder(state, newOrder) {
            state.order = newOrder;
        },

        setLoading(state, value) {
            const name = value[0];
            const data = value[1];

            if (typeof data !== 'boolean') {
                return;
            }

            if (state.loading[name] !== undefined) {
                state.loading[name] = data;
            }
        },

        setSavedSuccessful(state, value) {
            state.savedSuccessful = value;
        },

        setVersionContext(state, versionContext) {
            state.versionContext = versionContext;
        },
    },
};
