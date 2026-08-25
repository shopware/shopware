import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import component from './index';

/**
 * @sw-package after-sales
 */

const orderFixture = {
    id: '1234',
    documents: [],
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
            isLoadingDocument: false,
            order: orderFixture,
            documentType: null,
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
                    getDocumentNumbersByTypes: () => [],
                    getPreferredFileFormat: (formats, defaultFormat) => formats[0] ?? defaultFormat,
                },
                numberRangeService: {
                    reserve: jest.fn().mockResolvedValue({ number: '1000' }),
                },
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'document_type') {
                            return {
                                search: jest.fn().mockResolvedValue(getCollection('document_type', documentTypeFixture)),
                            };
                        }

                        if (entity === 'media') {
                            return {
                                get: jest.fn(),
                            };
                        }

                        return null;
                    },
                },
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-upload-document-modal', () => {
    it('renders the empty state no document type is selected', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-order-upload-document-modal__file-format').classes()).toContain('is--disabled');
        expect(wrapper.find('.sw-order-upload-document-modal__empty-state').exists()).toBeTruthy();
        expect(wrapper.find('.sw-order-upload-document-modal__upload-button').attributes().disabled).toBeDefined();
        expect(wrapper.find('.sw-order-upload-document-modal__upload-button-arrow').attributes().disabled).toBe('true');
    });

    it('changes selectable file formats depending on the document type', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const documentTypeSelectInput = wrapper.find(
            '.sw-order-upload-document-modal__document-type .mt-select-selection-list__input',
        );
        await documentTypeSelectInput.trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__document-type .mt-select-option--invoice').trigger('click');
        await flushPromises();

        const documentFormatSelectInput = wrapper.find(
            '.sw-order-upload-document-modal__file-format .mt-select-selection-list__input',
        );
        await documentFormatSelectInput.trigger('click');
        await flushPromises();

        const documentFormatInvoiceListElements = wrapper.findAll(
            '.sw-order-upload-document-modal__file-format .mt-select-result',
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

        await wrapper.find('.sw-order-upload-document-modal__document-type .mt-select-option--1').trigger('click');
        await flushPromises();

        await documentFormatSelectInput.trigger('click');
        await flushPromises();

        const documentFormatCancellationInvoiceListElements = wrapper.findAll(
            '.sw-order-upload-document-modal__file-format .mt-select-result',
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
            '.sw-order-upload-document-modal__document-type .mt-select-selection-list__input',
        );
        await documentTypeSelectInput.trigger('click');
        await flushPromises();

        const documentTypeInvoiceOption = wrapper.find(
            '.sw-order-upload-document-modal__document-type .mt-select-option--invoice',
        );
        await documentTypeInvoiceOption.trigger('click');
        await flushPromises();

        const documentFileFormatSelectedElements = wrapper.findAll(
            '.sw-order-upload-document-modal__file-format .mt-select-selection-list__item-holder',
        );
        expect(documentFileFormatSelectedElements).toEqual([]);
    });

    it('keeps the form hidden while reserving the document number for a selected type', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper
            .find('.sw-order-upload-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__document-type .mt-select-option--invoice').trigger('click');

        const documentModal = wrapper.find('.sw-order-upload-document-modal');

        expect(documentModal.attributes()['is-loading']).toBe('true');
        expect(wrapper.find('.sw-order-upload-document-modal__content').exists()).toBe(false);

        await flushPromises();

        expect(documentModal.attributes()['is-loading']).toBe('false');

        const documentNumberInput = wrapper.find('.sw-order-upload-document-modal__document-number input');
        expect(documentNumberInput.attributes()['value']).toBe('1000');
    });

    it('emits the document configuration when creating a V2 document', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper
            .find('.sw-order-upload-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__document-type .mt-select-option--invoice').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__file-format .mt-select-selection-list__input').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__file-format .mt-select-option--html').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__file-format .mt-select-selection-list__input').trigger('click');
        await flushPromises();

        const file = {
            name: 'document.pdf',
            type: 'application/pdf',
        };

        await wrapper.setData({ selectedDocumentFile: file });
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__upload-button').trigger('click');

        expect(wrapper.emitted()['document-upload']).toBeTruthy();
        expect(wrapper.emitted()['document-upload'][0][0]).toStrictEqual({
            documentComment: '',
            documentDate: '1970-01-01T00:00:00.000Z',
            documentMediaFileId: null,
            documentNumber: '1000',
            requestedFileFormats: ['html'],
        });
        expect(wrapper.emitted()['document-upload'][0][2]).toStrictEqual(file);
    });

    it('emits the send action when using the generate and send menu item', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper
            .find('.sw-order-upload-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__document-type .mt-select-option--invoice').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__file-format .mt-select-selection-list__input').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__file-format .mt-select-option--pdf').trigger('click');
        await flushPromises();

        const file = {
            name: 'document.pdf',
            type: 'application/pdf',
        };

        await wrapper.setData({ selectedDocumentFile: file });
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__upload-button-send').trigger('click');

        expect(wrapper.emitted()['document-upload']).toBeTruthy();
        expect(wrapper.emitted()['document-upload'][0][1]).toBe('send');
    });

    it('should clear the uploaded file when changing the document type', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper
            .find('.sw-order-upload-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__document-type .mt-select-option--invoice').trigger('click');
        await flushPromises();

        await wrapper.setData({
            selectedDocumentFile: {
                name: 'document.pdf',
                type: 'application/pdf',
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-order-upload-document-modal__file-input').attributes('source')).toBeDefined();

        await wrapper
            .find('.sw-order-upload-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__document-type .mt-select-option--storno').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-order-upload-document-modal__file-input').attributes('source')).toBeUndefined();
    });

    it('should clear the file format selection when changing the document type', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper
            .find('.sw-order-upload-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__document-type .mt-select-option--invoice').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__file-format .mt-select-selection-list__input').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__file-format .mt-select-option--pdf').trigger('click');
        await flushPromises();

        expect(
            wrapper
                .find('.sw-order-upload-document-modal__file-format .mt-select-selection-list__input')
                .attributes('value'),
        ).toBe('pdf--snippet');

        await wrapper
            .find('.sw-order-upload-document-modal__document-type .mt-select-selection-list__input')
            .trigger('click');
        await flushPromises();

        await wrapper.find('.sw-order-upload-document-modal__document-type .mt-select-option--storno').trigger('click');
        await flushPromises();

        expect(
            wrapper
                .find('.sw-order-upload-document-modal__file-format .mt-select-selection-list__input')
                .attributes('value'),
        ).toBeUndefined();
    });
});
