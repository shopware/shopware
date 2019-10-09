export default {
    namespaced: true,
    state: {
        isExpanded: true
    },

    mutations: {
        collapseSidebar(state) {
            state.isExpanded = false;
        },

        expandSidebar(state) {
            state.isExpanded = true;
        }
    }
};
