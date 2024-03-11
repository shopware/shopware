import { mount } from '@vue/test-utils';
import swOrderSelectDocumentTypeModal from 'src/module/sw-order/component/sw-order-select-document-type-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/form/sw-radio-field';
import 'src/app/component/form/field-base/sw-base-field';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package customer-order
 */

Shopware.Component.register('sw-order-select-document-type-modal', swOrderSelectDocumentTypeModal);

const orderFixture = {
    id: '1234',
    documents: [],
    taxStatus: 'gross',
    orderNumber: '10000',
    amountNet: 80,
    amountGross: 100,
    lineItems: [],
};

const documentFixture = {
    orderId: 'order1',
    sent: true,
    documentMediaFileId: null,
    documentType: {
        id: '1',
        name: 'Invoice',
        technicalName: 'invoice',
    },
    config: {
        documentNumber: '1000',
    },
};

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

const documentTypeFixture = [
    {
        id: '0',
        name: 'Delivery note',
        technicalName: 'delivery_note',
        translated: {
            name: 'Delivery note',
        },
    },
    {
        id: '1',
        name: 'Invoice',
        technicalName: 'invoice',
        translated: {
            name: 'Invoice',
        },
    },
    {
        id: '2',
        name: 'Cancellation invoice',
        technicalName: 'storno',
        translated: {
            name: 'Cancellation invoice',
        },
    },
    {
        id: '3',
        name: 'Credit note',
        technicalName: 'credit_note',
        translated: {
            name: 'Credit note',
        },
    },
];

async function createWrapper(customData = {}) {
    return mount(await wrapTestComponent('sw-order-select-document-type-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-radio-field': await wrapTestComponent('sw-radio-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-field-error': true,
                'sw-help-text': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => ({
                        search: () => {
                            if (entity === 'document_type') {
                                return Promise.resolve(getCollection('document_type', documentTypeFixture));
                            }

                            return Promise.resolve([]);
                        },
                        searchIds: () => Promise.resolve(getCollection('document', customData.documents || [])),
                        get: () => Promise.resolve({}),
                    }),
                },
            },
        },
        props: {
            order: { ...orderFixture, ...customData.order },
            value: {},
        },
    });
}

describe('src/module/sw-order/component/sw-order-select-document-type-modal', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable storno and credit note if there is no invoice exists', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const documentTypeRadioOptions = wrapper.findAll('.sw-field__radio-option');
        expect(documentTypeRadioOptions).toHaveLength(4);

        // Delivery note
        expect(documentTypeRadioOptions[0].find('input')
            .attributes().disabled).toBeUndefined();

        // Invoice
        expect(documentTypeRadioOptions[1].find('input')
            .attributes().disabled).toBeUndefined();

        // Cancellation invoice
        expect(documentTypeRadioOptions[2].find('input').element.disabled).toBe(true);

        // Credit note
        expect(documentTypeRadioOptions[3].find('input').element.disabled).toBe(true);

        const helpTextStorno = documentTypeRadioOptions[2].findComponent('sw-help-text-stub');

        expect(helpTextStorno.attributes().text)
            .toBe('sw-order.components.selectDocumentTypeModal.helpText.storno');

        const helpTextCredit = documentTypeRadioOptions.at(3).find('sw-help-text-stub');
        expect(helpTextCredit.attributes().text)
            .toBe('sw-order.components.selectDocumentTypeModal.helpText.credit_note');
    });

    it('should enable cancellation invoice if there is at least one invoice exists', async () => {
        const wrapper = await createWrapper({ documents: [documentFixture] });
        await flushPromises();

        const documentTypeRadioOptions = wrapper.findAll('.sw-field__radio-option');
        expect(documentTypeRadioOptions).toHaveLength(4);

        // Delivery note
        expect(documentTypeRadioOptions.at(0).find('input')
            .attributes().disabled).toBeUndefined();

        // Invoice
        expect(documentTypeRadioOptions.at(1).find('input')
            .attributes().disabled).toBeUndefined();

        // Cancellation invoice
        expect(documentTypeRadioOptions.at(2).find('input')
            .attributes().disabled).toBeUndefined();

        // Credit note
        expect(documentTypeRadioOptions.at(3).find('input')
            .element.disabled).toBe(true);
    });

    it('should enable credit note if there is at least one invoice exists and order has credit item', async () => {
        const wrapper = await createWrapper({
            documents: [documentFixture],
            order: {
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
            },
        });
        await flushPromises();

        const documentTypeRadioOptions = wrapper.findAll('.sw-field__radio-option');
        expect(documentTypeRadioOptions).toHaveLength(4);

        documentTypeRadioOptions.forEach(option => {
            expect(option.find('input')
                .attributes().disabled).toBeUndefined();
        });
    });
});
