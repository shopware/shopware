import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const orderFixture = {
    id: 'order1',
    documents: [],
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
    return mount(await wrapTestComponent('sw-order-document-settings-invoice-modal', { sync: true }), {
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
                    reserve: () => Promise.resolve({ number: 1337 }),
                },
            },
        },
        props: {
            order: orderFixture,
            currentDocumentType: {},
            isLoadingDocument: false,
            isLoadingPreview: false,
        },
    });
}

describe('src/module/sw-order/component/sw-order-document-settings-invoice-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should allow any text input in the document number field', async () => {
        const documentNumberFieldInput = wrapper.findByLabel('sw-order.documentModal.labelDocumentNumber');
        expect(documentNumberFieldInput.exists()).toBeTruthy();

        await documentNumberFieldInput.setValue('Prefix-1000-Suffix');
        expect(documentNumberFieldInput.element.value).toBe('Prefix-1000-Suffix');
    });
});
