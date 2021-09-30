export default {
    namespaced: true,

    state() {
        return {
            searchPreferences: [],
            userSearchPreferences: null,
        };
    },

    mutations: {
        setSearchPreferences(state, searchPreferences) {
            state.searchPreferences = searchPreferences;
        },
        setUserSearchPreferences(state, userSearchPreferences) {
            state.userSearchPreferences = userSearchPreferences;
        },
    },
};
