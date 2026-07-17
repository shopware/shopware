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
        order = orderFixture,
        documentTypeCollection = documentTypeFixture,
        reserveMock = jest.fn().mockResolvedValue({ number: '1000' }),
        searchIdsResult = [],
        supportedDocumentTypes = {
            invoice: {
                formats: [
                    'pdf',
                    'html',
                    'zugferd_embedded_pdf',
                    'zugferd_xml',
                ],
            },
        },
        ...componentProps
    } = props;

    return mount(component, {
        props: {
            isLoadingDocument: false,
            isLoadingPreview: false,
            order,
            value: null,
            ...componentProps,
        },
        global: {
            stubs: {
                'sw-modal': {
                    props: ['isLoading'],
                    template:
                        '<div class="sw-modal" :data-is-loading="isLoading"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-button-group': await wrapTestComponent('sw-button-group', { sync: true }),
                'sw-context-menu-item': {
                    emits: ['click'],
                    template:
                        '<button type="button" class="sw-context-menu-item" @click="$emit(\'click\')"><slot></slot></button>',
                },
                'sw-multi-select': await wrapTestComponent('sw-multi-select'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-field-error': true,
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-highlight-text': true,
                'mt-button': true,
                'mt-datepicker': true,
                'mt-icon': true,
                'mt-select': true,
                'mt-text-field': true,
                'mt-textarea': true,
                'router-link': true,
            },
            provide: {
                documentV2Service: {
                    getAvailableTypes: jest.fn().mockResolvedValue({
                        data: {
                            documentTypes: supportedDocumentTypes,
                        },
                    }),
                },
                numberRangeService: {
                    reserve: reserveMock,
                },
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'document_type') {
                            return {
                                search: jest.fn().mockResolvedValue(getCollection('document_type', documentTypeCollection)),
                            };
                        }

                        if (entity === 'document') {
                            return {
                                searchIds: jest.fn().mockResolvedValue(getCollection('document', searchIdsResult)),
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
    it('renders the empty state until a document type is selected', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-order-create-document-modal__empty-state').exists()).toBeTruthy();
        expect(wrapper.find('.sw-order-create-document-modal__create-button').attributes().disabled).toBeDefined();
    });

    it('only exposes V2-supported document types and formats', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.documentTypeOptions).toHaveLength(1);
        expect(wrapper.vm.documentTypeOptions[0]).toEqual(
            expect.objectContaining({
                value: 'invoice',
            }),
        );

        await wrapper.setData({ documentTypeId: 'invoice' });
        await flushPromises();

        expect(wrapper.vm.fileFormatOptions).toEqual([
            {
                label: 'sw-order.components.createDocumentModal.fileFormats.pdf',
                value: 'pdf',
            },
            {
                label: 'sw-order.components.createDocumentModal.fileFormats.html',
                value: 'html',
            },
            {
                label: 'sw-order.components.createDocumentModal.fileFormats.zugferdEmbeddedPdf',
                value: 'zugferd_embedded_pdf',
            },
            {
                label: 'sw-order.components.createDocumentModal.fileFormats.zugferdXml',
                value: 'zugferd_xml',
            },
        ]);
    });

    it('does not preselect file formats after selecting a document type', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ documentTypeId: 'invoice' });
        await flushPromises();

        expect(wrapper.vm.documentConfig.documentNumber).toBe('1000');
        expect(wrapper.vm.selectedFileFormats).toEqual([]);
    });

    it('keeps the form hidden while reserving the document number for a selected type', async () => {
        let resolveReserve;
        const reserveMock = jest.fn().mockImplementation(
            () =>
                new Promise((resolve) => {
                    resolveReserve = resolve;
                }),
        );

        const wrapper = await createWrapper({ reserveMock });
        await flushPromises();

        await wrapper.setData({ documentTypeId: 'invoice' });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-order-create-document-modal__content').exists()).toBeFalsy();
        expect(wrapper.find('.sw-order-create-document-modal__empty-state').exists()).toBeFalsy();
        expect(wrapper.find('.sw-modal').attributes()['data-is-loading']).toBe('true');

        resolveReserve({ number: '1000' });
        await flushPromises();

        expect(wrapper.find('.sw-order-create-document-modal__content').exists()).toBeTruthy();
        expect(wrapper.find('.sw-modal').attributes()['data-is-loading']).toBe('false');
    });

    it('does not mark the invoice selector as required while configuring credit-note documents', async () => {
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

        await wrapper.setData({ documentTypeId: 'credit-note' });
        await flushPromises();

        expect(wrapper.find('.sw-order-create-document-modal__invoice-number').attributes().required).toBeUndefined();
    });

    it('emits the selected formats when creating a V2 document', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ documentTypeId: 'invoice' });
        await flushPromises();
        await wrapper.setData({
            selectedFileFormats: [
                'pdf',
                'html',
            ],
        });
        await flushPromises();

        await wrapper.vm.onCreateDocument();

        expect(wrapper.emitted()['document-create']).toBeTruthy();
        expect(wrapper.emitted()['document-create'][0][0].custom.invoiceNumber).toBe('1000');
        expect(wrapper.emitted()['document-create'][0][0].requestedFormats).toEqual([
            'pdf',
            'html',
        ]);
    });

    it('emits the send action when using the generate and send menu item', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ documentTypeId: 'invoice' });
        await flushPromises();
        await wrapper.setData({
            selectedFileFormats: [
                'pdf',
            ],
        });
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__create-button-send').trigger('click');

        expect(wrapper.emitted()['document-create']).toBeTruthy();
        expect(wrapper.emitted()['document-create'][0][1]).toBe('send');
    });

    it('emits the download action when using the generate and download menu item', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ documentTypeId: 'invoice' });
        await flushPromises();
        await wrapper.setData({
            selectedFileFormats: [
                'pdf',
            ],
        });
        await flushPromises();

        await wrapper.find('.sw-order-create-document-modal__create-button-download').trigger('click');

        expect(wrapper.emitted()['document-create']).toBeTruthy();
        expect(wrapper.emitted()['document-create'][0][1]).toBe('download');
    });
});
