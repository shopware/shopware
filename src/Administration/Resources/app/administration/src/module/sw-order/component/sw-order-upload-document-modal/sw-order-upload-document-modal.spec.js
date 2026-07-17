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
                formats: ['pdf'],
            },
        },
        ...componentProps
    } = props;

    return mount(component, {
        props: {
            isLoadingDocument: false,
            order: orderFixture,
            value: null,
            ...componentProps,
        },
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
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
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-media-modal-v2': true,
                'sw-field-error': true,
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-highlight-text': true,
                'mt-button': true,
                'mt-datepicker': true,
                'mt-empty-state': true,
                'mt-icon': true,
                'mt-select': true,
                'mt-text-field': true,
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
    it('renders the empty state until a document type is selected', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-order-upload-document-modal__empty-state').exists()).toBeTruthy();
    });

    it('only exposes V2-supported document types and formats', async () => {
        const wrapper = await createWrapper({
            supportedDocumentTypes: {
                invoice: {
                    formats: [
                        'html',
                        'pdf',
                        'zugferd_embedded_pdf',
                    ],
                },
            },
        });
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
        ]);
        expect(wrapper.vm.fileAcceptTypes).toBe('application/pdf,text/html');

        await wrapper.setData({ selectedFileFormat: 'html' });
        await flushPromises();

        expect(wrapper.vm.fileAcceptTypes).toBe('text/html');
    });

    it('emits the uploaded file when uploading a custom document', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const file = {
            name: 'document.pdf',
            type: 'application/pdf',
        };

        await wrapper.setData({ documentTypeId: 'invoice' });
        await flushPromises();

        await wrapper.setData({
            selectedFileFormat: 'pdf',
        });
        await flushPromises();

        await wrapper.setData({ selectedDocumentFile: file });
        await flushPromises();

        await wrapper.vm.onUploadDocument();

        const emittedFile = wrapper.emitted()['document-create'][0][3];

        expect(wrapper.emitted()['document-create']).toBeTruthy();
        expect(wrapper.emitted()['document-create'][0][0].requestedFormats).toEqual(['pdf']);
        expect(emittedFile).toEqual(file);
    });
});
