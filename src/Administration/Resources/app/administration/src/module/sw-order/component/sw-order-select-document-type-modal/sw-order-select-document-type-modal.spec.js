import { mount } from '@vue/test-utils';
import 'src/app/component/base/sw-button';
import 'src/app/component/form/sw-radio-field';
import 'src/app/component/form/field-base/sw-base-field';
import EntityCollection from 'src/core/data/entity-collection.data';
import { DOCUMENT_TYPES } from '../../order.types';
import { REQUIRES_INVOICE } from './index';

/**
 * @sw-package checkout
 */

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
    ...Object.values(DOCUMENT_TYPES).map((type, index) => ({
        id: index,
        name: type,
        technicalName: type,
        translated: {
            name: type,
        },
    })),
];

const documentTypeRepsoitoryMock = {
    search: jest.fn().mockResolvedValue(getCollection('document_type', documentTypeFixture)),
    get: jest.fn().mockResolvedValue({}),
};

const documentRepositoryMock = {
    searchIds: jest.fn().mockResolvedValue(getCollection('document', [documentFixture])),
};

const defaultProps = {
    order: orderFixture,
    value: {},
};

async function createWrapper(props = defaultProps) {
    return mount(
        await wrapTestComponent('sw-order-select-document-type-modal', {
            sync: true,
        }),
        {
            props,
            global: {
                stubs: {
                    'sw-modal': {
                        template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                    },
                    'sw-radio-field': await wrapTestComponent('sw-radio-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': true,
                    'sw-help-text': true,
                    'router-link': true,
                    'sw-loader': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (entity) => {
                            if (entity === 'document_type') {
                                return documentTypeRepsoitoryMock;
                            }

                            if (entity === 'document') {
                                return documentRepositoryMock;
                            }

                            return null;
                        },
                    },
                },
            },
        },
    );
}

describe('src/module/sw-order/component/sw-order-select-document-type-modal', () => {
    it('should enable credit note if there is at least one invoice exists and order has credit item', async () => {
        const wrapper = await createWrapper({
            order: {
                ...orderFixture,
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

        documentTypeRadioOptions.forEach((option) => {
            expect(option.find('input').attributes().disabled).toBeUndefined();
        });
    });

    it('should fetch document with correct criteria', async () => {
        await createWrapper();
        await flushPromises();

        expect(documentRepositoryMock.searchIds).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: expect.arrayContaining([
                    expect.objectContaining({
                        field: 'order.id',
                        type: 'equals',
                        value: '1234',
                    }),
                    expect.objectContaining({
                        field: 'documentType.technicalName',
                        type: 'equalsAny',
                        value: [
                            DOCUMENT_TYPES.INVOICE,
                            DOCUMENT_TYPES.ZUGFERD_INVOICE,
                            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
                        ].join('|'),
                    }),
                ]),
            }),
        );
    });

    it('should add help text & disabled if document type needs invoice to be created first', async () => {
        documentRepositoryMock.searchIds.mockResolvedValueOnce(getCollection('document', []));

        const wrapper = await createWrapper();
        await flushPromises();

        const optionsWithHelpText = wrapper.vm.documentTypes.filter((option) => {
            return !!option?.helpText;
        });

        const disabledOptions = wrapper.vm.documentTypes.filter((option) => {
            return option.disabled;
        });

        expect([...optionsWithHelpText].map((option) => option.technicalName).sort()).toStrictEqual(REQUIRES_INVOICE.sort());
        expect([...disabledOptions].map((option) => option.technicalName).sort()).toStrictEqual(REQUIRES_INVOICE.sort());

        expect(wrapper.findAll('sw-help-text-stub')).toHaveLength(2);
        expect(wrapper.findAll('.is--disabled')).toHaveLength(2);

        await wrapper.find('.sw-order-select-document-type-modal__type-switch input').setChecked(true);

        expect(wrapper.findAll('sw-help-text-stub')).toHaveLength(4);
        expect(wrapper.findAll('.is--disabled')).toHaveLength(4);
    });

    it('should not add help text & disable if invoice is already created', async () => {
        const wrapper = await createWrapper({
            order: {
                ...orderFixture,
                lineItems: [
                    {
                        type: 'credit',
                    },
                ],
            },
        });
        await flushPromises();

        expect(wrapper.findAll('sw-help-text-stub')).toHaveLength(0);
        expect(wrapper.findAll('.is--disabled')).toHaveLength(0);

        await wrapper.find('.sw-order-select-document-type-modal__type-switch input').setChecked(true);

        expect(wrapper.findAll('sw-help-text-stub')).toHaveLength(0);
        expect(wrapper.findAll('.is--disabled')).toHaveLength(0);
    });

    it('should filter displayed document types based on if they are zugferd types or not', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const standardOptions = wrapper
            .findAll('.sw-field__radio-option')
            .map((option) => option.text())
            .sort();

        expect(standardOptions).toStrictEqual([
            DOCUMENT_TYPES.CREDIT_NOTE,
            DOCUMENT_TYPES.DELIVERY_NOTE,
            DOCUMENT_TYPES.INVOICE,
            DOCUMENT_TYPES.CANCELLATION_INVOICE,
        ]);

        await wrapper.find('.sw-order-select-document-type-modal__type-switch input').setChecked(true);

        const zugferdOptions = wrapper
            .findAll('.sw-field__radio-option')
            .map((option) => option.text())
            .sort();

        expect(zugferdOptions).toStrictEqual([
            DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
            DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CANCELLATION_INVOICE,
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
            DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
            DOCUMENT_TYPES.ZUGFERD_INVOICE,
        ]);
    });
});
