import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-document-settings-storno-modal';
import 'src/module/sw-order/component/sw-order-document-settings-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-button-group';
import 'src/app/component/form/field-base/sw-base-field';

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
                }
            }
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
                }
            }
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
                }
            }
        }
    ],
    currency: {
        shortName: 'EUR',
    },
    taxStatus: 'gross',
    orderNumber: '10000',
    amountNet: 80,
    amountGross: 100,
    lineItems: []
};

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-order-document-settings-storno-modal'), {
        stubs: {
            'sw-order-document-settings-modal': Shopware.Component.build('sw-order-document-settings-modal'),
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>'
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-text-field': true,
            'sw-datepicker': true,
            'sw-checkbox-field': true,
            'sw-switch-field': true,
            'sw-context-button': {
                template: '<div class="sw-context-button"><slot></slot></div>'
            },
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-button-group': Shopware.Component.build('sw-button-group'),
            'sw-context-menu-item': true,
            'sw-upload-listener': true,
            'sw-textarea-field': true,
            'sw-icon': true,
            'sw-select-field': {
                model: {
                    prop: 'value',
                    event: 'change'
                },
                template: '<select class="sw-select-field" :value="value" @change="$emit(\'change\', $event.target.value)"><slot></slot></select>',
                props: ['value', 'options']
            },
        },
        provide: {
            numberRangeService: {
                reserve: () => Promise.resolve({})
            },
            mediaService: {},
        },
        propsData: {
            order: orderFixture,
            isLoading: false,
            currentDocumentType: {},
            isLoadingDocument: false,
            isLoadingPreview: false,
        }
    });
}

describe('src/module/sw-order/component/sw-order-document-settings-storno-modal', () => {
    beforeEach(() => {
        global.activeFeatureFlags = ['FEATURE_NEXT_7530'];
    });

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show only invoice numbers in invoice number select field', () => {
        const wrapper = createWrapper();

        const invoiceOptions = wrapper.find('.sw-order-document-settings-storno-modal__invoice-select')
            .findAll('option');

        const invoiceNumbers = ['1000', '1001'];

        invoiceOptions.wrappers.forEach((option, index) => {
            expect(option.attributes().value).toEqual(invoiceNumbers[index]);
        });
    });

    it('should disable create button if there is no selected invoice', () => {
        const wrapper = createWrapper();

        const createButton = wrapper.find('.sw-order-document-settings-modal__create');
        expect(createButton.attributes().disabled).toBe('disabled');

        const createContextMenu = wrapper.find('.sw-context-button');
        expect(createContextMenu.attributes().disabled).toBe('disabled');
    });

    it('should enable create button if there is at least one selected invoice', async () => {
        const wrapper = createWrapper();

        const invoiceOptions = wrapper.find('.sw-order-document-settings-storno-modal__invoice-select')
            .findAll('option');

        await invoiceOptions.at(0).setSelected();

        const createButton = wrapper.find('.sw-order-document-settings-modal__create');
        expect(createButton.attributes().disabled).toBeUndefined();

        const createContextMenu = wrapper.find('.sw-context-button');
        expect(createContextMenu.attributes().disabled).toBeUndefined();
    });
});
