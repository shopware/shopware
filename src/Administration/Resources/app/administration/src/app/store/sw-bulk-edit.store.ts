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

interface OrderDeleteDocument {
    isChanged: boolean;
    value: Array<{
        id: string;
        name: string;
        technicalName: string;
        translated?: { name?: string; customFields?: unknown };
        selected: boolean;
    }>;
}

interface DocumentGenerationFailedItem {
    orderId: string;
    documentType: string;
    errorCode?: string;
    detail?: string;
}

interface DocumentGenerationResult {
    requested: number;
    failed: number;
    skipped: number;
    failedItems: DocumentGenerationFailedItem[];
}

interface SwBulkState {
    isFlowTriggered: boolean;
    orderDocuments: {
        invoice: OrderDocument;
        storno: OrderDocument;
        delivery_note: OrderDocument;
        credit_note: OrderDocument;
        download: OrderDownloadDocument;
        delete: OrderDeleteDocument;
    };
    selectedIds: string[];
    documentGenerationResult: DocumentGenerationResult;
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
                delete: {
                    isChanged: false,
                    value: [],
                },
            },
            selectedIds: [],
            documentGenerationResult: {
                requested: 0,
                failed: 0,
                skipped: 0,
                failedItems: [],
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
                  type: Exclude<keyof SwBulkState['orderDocuments'], 'download' | 'delete'>;
                  value: OrderDocument['value'];
              }
            | { type: 'download'; value: OrderDownloadDocument['value'] }
            | { type: 'delete'; value: OrderDeleteDocument['value'] }) {
            this.orderDocuments[type].value = value;
        },
        resetOrderDocumentsIsChanged() {
            Object.keys(this.orderDocuments).forEach((type) => {
                this.setOrderDocumentsIsChanged({
                    type: type as keyof SwBulkState['orderDocuments'],
                    isChanged: false,
                });
            });
        },
        setDocumentGenerationResult(
            requested: number,
            failed: number,
            skipped = 0,
            failedItems: DocumentGenerationFailedItem[] = [],
        ) {
            this.documentGenerationResult = {
                requested,
                failed,
                skipped,
                failedItems,
            };
        },
        resetDocumentGenerationResult() {
            this.documentGenerationResult = {
                requested: 0,
                failed: 0,
                skipped: 0,
                failedItems: [],
            };
        },
    },

    getters: {
        documentTypeConfigs(state) {
            return Object.entries(state.orderDocuments)
                .filter(
                    ([
                        key,
                        value,
                    ]) => key !== 'download' && key !== 'delete' && value.isChanged === true,
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
