/**
 * @sw-package framework
 */

interface OrderDocument {
    isChanged: boolean;
    value: {
        documentDate: string;
        documentComment: string | null;
        forceDocumentCreation: boolean;
        custom?: {
            deliveryDate: string;
            deliveryNoteDate: string;
        };
    };
}

interface OrderDownloadDocument {
    isChanged: boolean;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    value: any[];
}

interface SwBulkState {
    isFlowTriggered: boolean;
    orderDocuments: {
        invoice: OrderDocument;
        storno: OrderDocument;
        delivery_note: OrderDocument;
        credit_note: OrderDocument;
        download: OrderDownloadDocument;
    };
}

const swBulkStore = Shopware.Store.register('swBulkEdit', {
    state() {
        const today = new Date().toISOString();

        return {
            isFlowTriggered: true,
            orderDocuments: {
                invoice: {
                    isChanged: false,
                    value: {
                        documentDate: today,
                        documentComment: null,
                        forceDocumentCreation: false,
                    },
                },
                storno: {
                    isChanged: false,
                    value: {
                        documentDate: today,
                        documentComment: null,
                        forceDocumentCreation: false,
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
                        forceDocumentCreation: false,
                    },
                },
                credit_note: {
                    isChanged: false,
                    value: {
                        documentDate: today,
                        documentComment: null,
                        forceDocumentCreation: false,
                    },
                },
                download: {
                    isChanged: false,
                    value: [],
                },
            },
        } as SwBulkState;
    },

    actions: {
        setIsFlowTriggered(isFlowTriggered: boolean) {
            this.isFlowTriggered = isFlowTriggered;
        },
        setOrderDocumentsIsChanged({ type, isChanged }: { type: keyof SwBulkState['orderDocuments']; isChanged: boolean }) {
            this.orderDocuments[type].isChanged = isChanged;
        },
        setOrderDocumentsValue({
            type,
            value,
        }:
            | {
                  type: Exclude<keyof SwBulkState['orderDocuments'], 'download'>;
                  value: OrderDocument['value'];
              }
            | { type: 'download'; value: OrderDownloadDocument['value'] }) {
            this.orderDocuments[type].value = value;
        },
    },

    getters: {
        documentTypeConfigs(state) {
            return Object.entries(state.orderDocuments)
                .filter(
                    ([
                        key,
                        value,
                    ]) => key !== 'download' && value.isChanged === true,
                )
                .map(
                    ([
                        key,
                        value,
                    ]) => ({
                        fileType: 'pdf',
                        type: key,
                        config: value.value,
                    }),
                );
        },
    },
});

/**
 * @private
 */
export default swBulkStore;

/**
 * @private
 */
export type SwBulkStore = ReturnType<typeof swBulkStore>;
