export default {
    namespaced: true,
    state: {
        currentUser: null
    },

    mutations: {
        setCurrentUser(state, user) {
            state.currentUser = user;
        },

        removeCurrentUser(state) {
            state.currentUser = null;
        }
    }
};
