/**
 * @package services-settings
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
