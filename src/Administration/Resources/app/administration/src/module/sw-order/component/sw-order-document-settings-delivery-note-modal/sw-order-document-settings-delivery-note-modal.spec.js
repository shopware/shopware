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
                id: '2',
                name: 'Delivery note',
                technicalName: 'delivery_note',
            },
            config: {
                documentNumber: '1001',
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
    lineItems: [
        {
            id: '3',
            type: 'credit',
            label: 'Credit item',
            quantity: 1,
            payload: [],
            price: {
                quantity: 1,
                totalPrice: -100,
                unitPrice: -100,
                calculatedTaxes: [
                    {
                        price: -100,
                        tax: -10,
                        taxRate: 10,
                    },
                ],
                taxRules: [
                    {
                        taxRate: 10,
                        percentage: 100,
                    },
                ],
            },
        },
    ],
};

const defaultProps = {
    order: orderFixture,
    currentDocumentType: {},
    isLoadingDocument: false,
    isLoadingPreview: false,
};

async function createWrapper(props = defaultProps) {
    return mount(await wrapTestComponent('sw-order-document-settings-delivery-note-modal', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-order-document-settings-modal': await wrapTestComponent('sw-order-document-settings-modal'),
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-checkbox-field': true,
                'sw-context-menu-item': true,
                'sw-upload-listener': true,
                'sw-loader': true,
                'sw-media-upload-v2': true,
                'sw-media-modal-v2': true,
                'router-link': true,
                'sw-ai-copilot-badge': true,
                'sw-button-group': await wrapTestComponent('sw-button-group'),
                'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
            },
            provide: {
                numberRangeService: {
                    reserve: () => Promise.resolve({ number: '1337' }),
                },
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-document-settings-delivery-note-modal', () => {
    it('should disable/enable create & preview buttons by delivery date value', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const documentConfig = {
            custom: {
                deliveryDate: '',
                deliveryNoteDate: new Date('2024-01-02').toISOString(),
            },
            documentNumber: 'PREVIEW_NUM_001',
            documentDate: new Date('2024-01-03').toISOString(),
            documentComment: '',
        };

        await wrapper.setData({
            documentConfig,
        });
        await flushPromises();

        expect(wrapper.find('.sw-order-document-settings-delivery-note-modal__document-number input').element.value).toBe(
            documentConfig.documentNumber,
        );
        expect(wrapper.find('.sw-order-document-settings-delivery-note-modal__document-date input').element.value).toBe(
            '2024/01/03',
        );
        expect(wrapper.find('.sw-order-document-settings-delivery-note-modal__delivery-date input').element.value).toBe('');

        expect(wrapper.find('.sw-order-document-settings-modal__preview-button').attributes()).toHaveProperty('disabled');
        expect(wrapper.find('.sw-order-document-settings-modal__preview-button-arrow').attributes('disabled')).toBe('true');
        expect(wrapper.find('.sw-order-document-settings-modal__create').attributes()).toHaveProperty('disabled');
        expect(wrapper.find('.sw-order-document-settings-modal__create-arrow').attributes('disabled')).toBe('true');

        await wrapper.setData({
            documentConfig: {
                ...documentConfig,
                custom: {
                    ...documentConfig.custom,
                    deliveryDate: new Date('2024-01-02').toISOString(),
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-order-document-settings-delivery-note-modal__delivery-date input').element.value).toBe(
            '2024/01/02',
        );

        expect(wrapper.find('.sw-order-document-settings-modal__preview-button').attributes()).not.toHaveProperty(
            'disabled',
        );
        expect(wrapper.find('.sw-order-document-settings-modal__preview-button-arrow').attributes('disabled')).toBe('false');
        expect(wrapper.find('.sw-order-document-settings-modal__create').attributes()).not.toHaveProperty('disabled');
        expect(wrapper.find('.sw-order-document-settings-modal__create-arrow').attributes('disabled')).toBe('false');
    });
});
