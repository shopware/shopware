import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import component from './index';

/**
 * @sw-package after-sales
 */

const orderFixture = {
    id: '1234',
    documents: [
        {
            type: 'invoice',
            number: '1000',
        },
    ],
    lineItems: [],
    orderNumber: '10000',
    salesChannelId: 'sales-channel-id',
    taxStatus: 'gross',
    versionId: 'order-version-id',
};

const documentTypeFixture = [
    {
        id: 'delivery-note',
        name: 'Delivery note',
        technicalName: 'delivery_note',
        translated: {
            name: 'Delivery note',
        },
    },
    {
        id: 'invoice',
        name: 'Invoice',
        technicalName: 'invoice',
        translated: {
            name: 'Invoice',
        },
    },
    {
        id: 'storno',
        name: 'Cancellation invoice',
        technicalName: 'storno',
        translated: {
            name: 'Cancellation invoice',
        },
    },
    {
        id: 'credit-note',
        name: 'Credit note',
        technicalName: 'credit_note',
        translated: {
            name: 'Credit note',
        },
    },
];

function getCollection(entity, collection) {
    return new EntityCollection(
        `/${entity}`,
        entity,
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null,
    );
}

async function createWrapper(props = {}) {
    const {
        order = orderFixture,
        numberRangeReserveMock = jest.fn().mockResolvedValue({ number: '1000' }),
        supportedDocumentTypes = {
            invoice: {
                formats: [
                    'pdf',
                    'html',
                    'zugferd_embedded_pdf',
                    'zugferd_xml',
                ],
            },
            storno: {
                formats: [
                    'pdf',
                    'html',
                ],
            },
        },
    } = props;

    return mount(component, {
        props: {
            order: order,
            documentType: props.documentType ?? null,
            isLoadingDocument: false,
            isLoadingPreview: false,
        },
        global: {
            provide: {
                documentV2Service: {
                    getAvailableDocumentTypes: () => Promise.resolve(supportedDocumentTypes),
                    createEmptyDocumentConfig: () => {
                        return {
                            documentComment: '',
                            documentDate: '1970-01-01T00:00:00.000Z',
                            documentNumber: '',
                            requestedFileFormats: [],
                        };
                    },
                    getDocumentFamily: (documentType) => documentType,
                    getDocumentNumberRangeType: (documentType) => documentType,
                    sortFileFormats: (formats) => formats,
                    getFileFormatSnippet: (format) => `${format}--snippet`,
                    getDocumentTypeSnippet: (technicalName) => `${technicalName}--type-snippet`,
                    getDocumentNumbersByTypes: (documents, types) =>
                        documents
                            .filter((document) => types.some((type) => document.type === type))
                            .map((document) => document.number),
                    getPreferredFileFormat: (formats, defaultFormat) => formats[0] ?? defaultFormat,
                },
                numberRangeService: {
                    reserve: numberRangeReserveMock,
                },
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'document_type') {
                            return {
                                search: jest.fn().mockResolvedValue(getCollection('document_type', documentTypeFixture)),
                            };
                        }

                        if (entity === 'document') {
                            return {
                                searchIds: jest.fn().mockResolvedValue(getCollection('document', [])),
                            };
                        }

                        return null;
                    },
                },
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-create-document-modal', () => {
    it('renders the empty state no document type is selected', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-order-create-document-modal__file-formats').classes()).toContain('is--disabled');
        expect(wrapper.find('.sw-order-create-document-modal__empty-state').exists()).toBeTruthy();
        expect(wrapper.find('.sw-order-create-document-modal__preview-button').attributes().disabled).toBeDefined();
        expect(wrapper.find('.sw-order-create-document-modal__preview-button-arrow').attributes().disabled).toBe('true');
        expect(wrapper.find('.sw-order-create-document-modal__create-button').attributes().disabled).toBeDefined();
        expect(wrapper.find('.sw-order-create-document-modal__create-button-arrow').attributes().disabled).toBe('true');
    });

    it('changes selectable file formats depending on the document type', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const documentTypeSelectInput = wrapper.find(
            '.sw-order-create-document-modal__document-type .mt-select-selection-list__input',
        );
        await documentTypeSelectInput.trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--invoice').trigger('click');
        await flushPromises();

        const documentFormatSelectInput = wrapper.find(
            '.sw-order-create-document-modal__file-formats .mt-select-selection-list__input',
        );
        await documentFormatSelectInput.trigger('click');
        await flushPromises();

        const documentFormatInvoiceListElements = wrapper.findAll(
            '.sw-order-create-document-modal__file-formats .mt-select-result',
        );

        const documentFormatInvoiceListElementsText = documentFormatInvoiceListElements.map((element) => {
            return element.text();
        });

        expect(documentFormatInvoiceListElementsText).toEqual([
            'pdf--snippet',
            'html--snippet',
            'zugferd_embedded_pdf--snippet',
            'zugferd_xml--snippet',
        ]);

        await documentTypeSelectInput.trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--storno').trigger('click');
        await flushPromises();

        await documentFormatSelectInput.trigger('click');
        await flushPromises();

        const documentFormatCancellationInvoiceListElements = wrapper.findAll(
            '.sw-order-create-document-modal__file-formats .mt-select-result',
        );

        const documentFormatCancellationInvoiceListElementsText = documentFormatCancellationInvoiceListElements.map(
            (element) => {
                return element.text();
            },
        );

        expect(documentFormatCancellationInvoiceListElementsText).toEqual([
            'pdf--snippet',
            'html--snippet',
        ]);
    });

    it('does not preselect file formats after selecting a document type', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const documentTypeSelectInput = wrapper.find(
            '.sw-order-create-document-modal__document-type .mt-select-selection-list__input',
        );
        await documentTypeSelectInput.trigger('click');
        await flushPromises();

        const documentTypeInvoiceOption = wrapper.find(
            '.sw-order-create-document-modal__document-type .mt-select-option--invoice',
        );
        await documentTypeInvoiceOption.trigger('click');
        await flushPromises();

        const documentFileFormatSelectedElements = wrapper.findAll(
            '.sw-order-create-document-modal__file-formats .mt-select-selection-list__item-holder',
        );
        expect(documentFileFormatSelectedElements).toEqual([]);
    });

    it('keeps the form hidden while reserving the document number for a selected type', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--invoice').trigger('click');

        const documentModal = wrapper.find('.sw-order-create-document-modal');

        expect(documentModal.attributes()['is-loading']).toBe('true');
        expect(wrapper.find('.sw-order-create-document-modal__content').exists()).toBe(false);

        await flushPromises();

        expect(documentModal.attributes()['is-loading']).toBe('false');

        const documentNumberInput = wrapper.find('.sw-order-create-document-modal__document-number input');
        expect(documentNumberInput.attributes()['value']).toBe('1000');
    });

    it('marks the invoice selector as required while configuring credit-note documents', async () => {
        const wrapper = await createWrapper({
            order: {
                ...orderFixture,
                documents: [
                    {
                        id: 'invoice-document-id',
                        config: {
                            custom: {
                                invoiceNumber: '1000',
                            },
                        },
                        documentType: {
                            technicalName: 'invoice',
                        },
                    },
                ],
                lineItems: [
                    {
                        id: 'credit-item-id',
                        type: 'credit',
                    },
                ],
            },
            searchIdsResult: [{ id: 'invoice-document-id' }],
            supportedDocumentTypes: {
                credit_note: {
                    formats: ['pdf'],
                },
            },
        });
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--credit-note').trigger('click');
        await flushPromises();

        expect(
            wrapper.find('.sw-order-create-document-modal__referenced-document-number label').classes('is--required'),
        ).toBeDefined();
    });

    it('shows an error on the invoice selector when the selected invoice has no credit item', async () => {
        const wrapper = await createWrapper({
            order: {
                ...orderFixture,
                documents: [
                    {
                        type: 'invoice',
                        number: '1000',
                    },
                ],
                lineItems: [],
            },
            supportedDocumentTypes: {
                credit_note: {
                    formats: ['pdf'],
                },
            },
        });
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--credit-note').trigger('click');
        await flushPromises();

        expect(wrapper.vm.referencedDocumentNumberErrorMessage).toEqual({
            detail: 'global.notification.notificationSaveErrorMessageRequiredField',
        });

        await wrapper
            .find('.sw-order-create-document-modal__referenced-document-number .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper
            .find(
                '.sw-order-create-document-modal__referenced-document-number .mt-select-result-list .mt-select-option--1000',
            )
            .trigger('click');
        await flushPromises();

        expect(wrapper.vm.referencedDocumentNumber).toBe('1000');
        expect(wrapper.vm.referencedDocumentNumberErrorMessage).toEqual({
            detail: 'sw-order.documentModal.errorInvoiceMissingCreditItem',
        });
        expect(wrapper.find('.sw-order-create-document-modal__referenced-document-number').text()).toContain(
            'sw-order.documentModal.errorInvoiceMissingCreditItem',
        );
    });

    it('emits the document configuration when creating a V2 document', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--invoice').trigger('click');
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__file-formats .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__file-formats .mt-select-option--html').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__file-formats .mt-select-option--pdf').trigger('click');
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__file-formats .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__create-button').trigger('click');

        expect(wrapper.emitted()['document-create']).toBeTruthy();
        expect(wrapper.emitted()['document-create'][0][0]).toStrictEqual({
            documentComment: '',
            documentDate: '1970-01-01T00:00:00.000Z',
            documentNumber: '1000',
            requestedFileFormats: [
                'html',
                'pdf',
            ],
        });
    });

    it('emits the send action when using the generate and send menu item', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--invoice').trigger('click');
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__file-formats .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__file-formats .mt-select-option--pdf').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__create-button-send').trigger('click');

        expect(wrapper.emitted()['document-create']).toBeTruthy();
        expect(wrapper.emitted()['document-create'][0][1]).toBe('send');
    });

    it('emits the download action when using the generate and download menu item', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--invoice').trigger('click');
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__file-formats .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__file-formats .mt-select-option--pdf').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__create-button-download').trigger('click');

        expect(wrapper.emitted()['document-create']).toBeTruthy();
        expect(wrapper.emitted()['document-create'][0][1]).toBe('download');
    });

    it('emits the document configuration when previewing a V2 document', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--invoice').trigger('click');
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__file-formats .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__file-formats .mt-select-option--html').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__file-formats .mt-select-option--pdf').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__preview-button').trigger('click');

        expect(wrapper.emitted()['preview-show']).toBeTruthy();
        expect(wrapper.emitted()['preview-show'][0][0]).toStrictEqual({
            documentComment: '',
            documentDate: '1970-01-01T00:00:00.000Z',
            documentNumber: '1000',
            requestedFileFormats: [
                'html',
                'pdf',
            ],
        });

        expect(wrapper.emitted()['preview-show'][0][1]).toBe('html');
    });

    it('emits the document configuration when explicitly previewing a pdf V2 document', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--invoice').trigger('click');
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__file-formats .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__file-formats .mt-select-option--html').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__file-formats .mt-select-option--pdf').trigger('click');
        await flushPromises();

        const previewOptions = wrapper.findAll('.sw-order-create-document-modal__preview-button-option');
        expect(previewOptions).toHaveLength(2);

        await previewOptions[1].trigger('click');

        expect(wrapper.emitted()['preview-show']).toBeTruthy();
        expect(wrapper.emitted()['preview-show'][0][0]).toStrictEqual({
            documentComment: '',
            documentDate: '1970-01-01T00:00:00.000Z',
            documentNumber: '1000',
            requestedFileFormats: [
                'html',
                'pdf',
            ],
        });

        expect(wrapper.emitted()['preview-show'][0][1]).toBe('pdf');
    });

    it('should clear the selected referenced document when switching between document types', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--storno').trigger('click');
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__referenced-document-number .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper
            .find(
                '.sw-order-create-document-modal__referenced-document-number .mt-select-result-list .mt-select-option--1000',
            )
            .trigger('click');
        await flushPromises();

        expect(
            wrapper
                .find('.sw-order-create-document-modal__referenced-document-number .mt-select-selection-list__input')
                .attributes('value'),
        ).toBe('1000');

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--invoice').trigger('click');
        await flushPromises();

        await wrapper
            .find('.sw-order-create-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__document-type .mt-select-option--storno').trigger('click');
        await flushPromises();

        expect(
            wrapper
                .find('.sw-order-create-document-modal__referenced-document-number .mt-select-selection-list__input')
                .attributes('value'),
        ).toBeUndefined();
    });
});
