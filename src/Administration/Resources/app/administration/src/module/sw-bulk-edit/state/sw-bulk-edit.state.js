export default {
    namespaced: true,

    state() {
        return {
            isFlowTriggered: true,
        };
    },

    mutations: {
        setIsFlowTriggered(state, isFlowTriggered) {
            state.isFlowTriggered = isFlowTriggered;
        },
    },
};
