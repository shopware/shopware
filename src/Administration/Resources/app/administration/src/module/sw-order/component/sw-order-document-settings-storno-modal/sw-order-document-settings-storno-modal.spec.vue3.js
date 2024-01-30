import { mount } from '@vue/test-utils_v3';

/**
 * @package customer-order
 */

const orderFixture = {
    id: 'order1',
    documents: [
        {
            orderId: 'order1',
            sent: true,
            documentMediaFileId: null,
            documentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
            },
            config: {
                documentNumber: 1000,
                custom: {
                    invoiceNumber: 1000,
                },
            },
        },
        {
            orderId: 'order1',
            sent: true,
            documentMediaFileId: null,
            documentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
            },
            config: {
                documentNumber: 1001,
                custom: {
                    invoiceNumber: 1001,
                },
            },
        },
        {
            orderId: 'order1',
            sent: true,
            documentMediaFileId: null,
            documentType: {
                id: '2',
                name: 'Delivery note',
                technicalName: 'delivery_note',
            },
            config: {
                documentNumber: 1001,
                custom: {
                    deliveryNoteNumber: 1001,
                },
            },
        },
    ],
    currency: {
        isoCode: 'EUR',
    },
    taxStatus: 'gross',
    orderNumber: '10000',
    amountNet: 80,
    amountGross: 100,
    lineItems: [],
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-document-settings-storno-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-order-document-settings-modal': await wrapTestComponent('sw-order-document-settings-modal', { sync: true }),
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-text-field': true,
                'sw-datepicker': true,
                'sw-checkbox-field': true,
                'sw-switch-field': true,
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-button': await wrapTestComponent('sw-button', { sync: true }),
                'sw-button-group': await wrapTestComponent('sw-button-group', { sync: true }),
                'sw-context-menu-item': true,
                'sw-upload-listener': true,
                'sw-textarea-field': true,
                'sw-icon': true,
                'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                'sw-field-error': true,
                'sw-loader': true,
            },
            provide: {
                numberRangeService: {
                    reserve: () => Promise.resolve({}),
                },
                mediaService: {},
            },
        },
        props: {
            order: orderFixture,
            isLoading: false,
            currentDocumentType: {},
            isLoadingDocument: false,
            isLoadingPreview: false,
        },
    });
}

describe('src/module/sw-order/component/sw-order-document-settings-storno-modal', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show only invoice numbers in invoice number select field', async () => {
        const wrapper = await createWrapper();

        const invoiceSelect = wrapper.find('.sw-order-document-settings-storno-modal__invoice-select');
        await invoiceSelect.trigger('click');

        const invoiceOptions = wrapper.find('.sw-order-document-settings-storno-modal__invoice-select')
            .findAll('option');

        expect(invoiceOptions.at(1).text()).toBe('1000');
        expect(invoiceOptions.at(2).text()).toBe('1001');
    });

    it('should disable create button if there is no selected invoice', async () => {
        const wrapper = await createWrapper();

        const createButton = wrapper.find('.sw-order-document-settings-modal__create');
        expect(createButton.attributes().disabled).toBeDefined();

        const createContextMenu = wrapper.find('.sw-context-button');
        expect(createContextMenu.attributes().disabled).toBeDefined();
    });

    it('should enable create button if there is at least one selected invoice', async () => {
        const wrapper = await createWrapper();

        const invoiceSelect = wrapper.find('.sw-order-document-settings-storno-modal__invoice-select');
        await invoiceSelect.trigger('click');

        const invoiceOptions = wrapper.find('.sw-order-document-settings-storno-modal__invoice-select')
            .findAll('option');

        await invoiceOptions.at(1).setSelected();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-order-document-settings-modal__create');
        expect(createButton.attributes().disabled).toBeUndefined();

        const createContextMenu = wrapper.find('.sw-context-button');
        expect(createContextMenu.attributes().disabled).toBeUndefined();
    });
});
