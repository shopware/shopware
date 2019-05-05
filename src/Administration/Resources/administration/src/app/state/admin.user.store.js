export default {
    namespaced: true,
    state: {
        currentUser: null,
        currentProfile: null
    },

    mutations: {
        setCurrentProfile(state, profile) {
            state.currentProfile = profile;
        },

        setCurrentUser(state, user) {
            state.currentUser = user;
        },

        removeCurrentProfile(state) {
            state.currentProfile = null;
        },

        removeCurrentUser(state) {
            state.currentUser = null;
        }
    }
};
