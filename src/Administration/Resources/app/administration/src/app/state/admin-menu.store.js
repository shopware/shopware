export default {
    namespaced: true,
    state: {
        isExpanded: true,
        expandedEntries: []
    },

    mutations: {
        expandMenuEntry(state, payload) {
            state.expandedEntries.push(payload);
        },

        collapseMenuEntry(state, payload) {
            state.expandedEntries = state.expandedEntries.filter((item) => {
                return item !== payload;
            });
        },

        collapseSidebar(state) {
            state.isExpanded = false;
        },

        expandSidebar(state) {
            state.isExpanded = true;
        }
    }
};
