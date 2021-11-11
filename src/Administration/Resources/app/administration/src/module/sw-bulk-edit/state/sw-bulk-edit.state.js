export default {
    namespaced: true,

    state() {
        return {
            isFlowTriggered: true,
            orderDocuments: {
                invoice: {
                    documentDate: null,
                    documentComment: null,
                },
                storno: {
                    documentDate: null,
                    documentComment: null,
                },
                delivery_note: {
                    documentDate: null,
                    documentDeliveryDate: null,
                    documentComment: null,
                },
                credit_note: {
                    documentDate: null,
                    documentComment: null,
                },
            },
        };
    },

    mutations: {
        setIsFlowTriggered(state, isFlowTriggered) {
            state.isFlowTriggered = isFlowTriggered;
        },
        setOrderDocuments(state, { type, payload }) {
            state.orderDocuments[type] = payload;
        },
    },
};
