/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';

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
    return mount(
        await wrapTestComponent('sw-order-document-settings-storno-modal', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-order-document-settings-modal': await wrapTestComponent('sw-order-document-settings-modal', {
                        sync: true,
                    }),
                    'sw-modal': {
                        template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                    },
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-text-field': true,
                    'sw-datepicker': true,
                    'sw-checkbox-field': true,

                    'sw-context-button': {
                        template: '<div class="sw-context-button"><slot></slot></div>',
                    },
                    'sw-button-group': await wrapTestComponent('sw-button-group', { sync: true }),
                    'sw-context-menu-item': true,
                    'sw-upload-listener': true,
                    'sw-textarea-field': true,
                    'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field', {
                        sync: true,
                    }),
                    'sw-field-error': true,
                    'sw-loader': true,
                    'sw-media-upload-v2': true,
                    'sw-media-modal-v2': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'router-link': true,
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
        },
    );
}

describe('src/module/sw-order/component/sw-order-document-settings-storno-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should show only invoice numbers in invoice number select field', async () => {
        const invoiceSelect = wrapper.find('.mt-select input');
        await invoiceSelect.trigger('click');

        const invoiceOptions = wrapper.find('.mt-select').findAll('.mt-highlight-text');
        const optionTexts = invoiceOptions.map((option) => option.text());

        expect(optionTexts).toContain('1000');
        expect(optionTexts).toContain('1001');
    });

    it('should allow any text input in the document number field', async () => {
        const documentNumberFieldInput = wrapper.findByLabel('sw-order.documentModal.labelDocumentStornoNumber');
        expect(documentNumberFieldInput.exists()).toBeTruthy();

        await documentNumberFieldInput.setValue('Prefix-1000-Suffix');
        expect(documentNumberFieldInput.element.value).toBe('Prefix-1000-Suffix');
    });

    it('should disable/enable create & preview buttons by selected invoice value', async () => {
        const documentConfig = {
            documentNumber: 'PREVIEW_NUM_001',
            documentDate: '2024/01/01',
        };

        await wrapper.setData({
            documentConfig,
        });
        await flushPromises();

        expect(wrapper.find('.sw-order-document-settings-storno-modal__document-number input').element.value).toBe(
            documentConfig.documentNumber,
        );
        expect(wrapper.find('.sw-order-document-settings-storno-modal__document-date input').element.value).toBe(
            documentConfig.documentDate,
        );
        expect(wrapper.find('.sw-order-document-settings-storno-modal__invoice-select input').element.value).toBe('');

        expect(wrapper.find('.sw-order-document-settings-modal__preview-button').attributes()).toHaveProperty('disabled');
        expect(wrapper.find('.sw-order-document-settings-modal__preview-button-arrow').attributes('disabled')).toBe('true');
        expect(wrapper.find('.sw-order-document-settings-modal__create').attributes()).toHaveProperty('disabled');
        expect(wrapper.find('.sw-order-document-settings-modal__create-arrow').attributes('disabled')).toBe('true');

        await wrapper.find('.sw-order-document-settings-storno-modal__invoice-select input').trigger('click');

        const invoiceOptions = wrapper.find('.mt-select-result-list-popover').findAll('.mt-select-result');
        await invoiceOptions.at(0).trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-order-document-settings-modal__preview-button').attributes()).not.toHaveProperty(
            'disabled',
        );
        expect(wrapper.find('.sw-order-document-settings-modal__preview-button-arrow').attributes('disabled')).toBe('false');
        expect(wrapper.find('.sw-order-document-settings-modal__create').attributes()).not.toHaveProperty('disabled');
        expect(wrapper.find('.sw-order-document-settings-modal__create-arrow').attributes('disabled')).toBe('false');
    });
});
