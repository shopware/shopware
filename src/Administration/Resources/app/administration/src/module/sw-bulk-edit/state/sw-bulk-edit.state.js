/**
 * @package system-settings
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    namespaced: true,

    state() {
        const today = (new Date()).toISOString();

        return {
            isFlowTriggered: true,
            orderDocuments: {
                invoice: {
                    isChanged: false,
                    value: {
                        documentDate: today,
                        documentComment: null,
                    },
                },
                storno: {
                    isChanged: false,
                    value: {
                        documentDate: today,
                        documentComment: null,
                    },
                },
                delivery_note: {
                    isChanged: false,
                    value: {
                        custom: {
                            deliveryDate: today,
                            deliveryNoteDate: today,
                        },
                        documentDate: today,
                        documentComment: null,
                    },
                },
                credit_note: {
                    isChanged: false,
                    value: {
                        documentDate: today,
                        documentComment: null,
                    },
                },
                download: {
                    isChanged: false,
                    value: [],
                },
            },
        };
    },

    mutations: {
        setIsFlowTriggered(state, isFlowTriggered) {
            state.isFlowTriggered = isFlowTriggered;
        },
        setOrderDocumentsIsChanged(state, { type, isChanged }) {
            state.orderDocuments[type].isChanged = isChanged;
        },
        setOrderDocumentsValue(state, { type, value }) {
            state.orderDocuments[type].value = value;
        },
    },

    getters: {
        documentTypeConfigs(state) {
            const documentTypeConfigs = [];

            Object.entries(state.orderDocuments).forEach(([key, value]) => {
                if (key === 'download') {
                    return;
                }
                if (value.isChanged === true) {
                    documentTypeConfigs.push({
                        fileType: 'pdf',
                        type: key,
                        config: value.value,
                    });
                }
            });

            return documentTypeConfigs;
        },
    },
};
