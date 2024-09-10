import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 */

const createWrapper = async () => {
    return mount(await wrapTestComponent('sw-order-product-select', { sync: true }), {
        props: {
            taxStatus: 'net',
            item: {
                priceDefinition: {
                    isCalculated: false,
                    taxRules: [{ taxRate: 0, percentage: 100 }],
                    price: 0,
                },
                price: {
                    taxRules: [{ taxRate: 0 }],
                    unitPrice: '...',
                    quantity: 1,
                    totalPrice: '...',
                },
                quantity: 1,
                unitPrice: 0,
                totalPrice: 0,
                precision: 2,
                label: '',
            },
            salesChannelId: '1',
        },
        global: {
            stubs: {
                'sw-text-field': true,
                'sw-entity-single-select': true,
                'sw-product-variant-info': true,
                'sw-select-result': true,
            },
        },

    });
};

describe('src/module/sw-order/component/sw-order-product-select', () => {
    beforeAll(() => {
        Shopware.Service().register('cartStoreService', () => {
            return {
                getLineItemTypes: () => {
                    return Object.freeze({
                        PRODUCT: 'product',
                        CREDIT: 'credit',
                        CUSTOM: 'custom',
                        PROMOTION: 'promotion',
                    });
                },

                getLineItemPriceTypes: () => {
                    return Object.freeze({
                        ABSOLUTE: 'absolute',
                        QUANTITY: 'quantity',
                    });
                },
            };
        });

        const mockResponses = global.repositoryFactoryMock.responses;

        mockResponses.addResponse({
            method: 'POST',
            url: '/search/product',
            status: 200,
            response: {
                data: [
                    {
                        attributes: {
                            id: '1',
                            name: 'Product test',
                            price: [
                                {
                                    gross: 110,
                                    net: 100,
                                },
                            ],
                            tax: {
                                taxRate: 7,
                            },
                        },
                        id: '1',
                        relationships: [],
                    },
                ],
                meta: {
                    total: 1,
                },
            },
        });
    });

    it('should show product select if item is new product', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            item: {
                ...wrapper.props().item,
                _isNew: true,
                type: 'product',
            },
        });

        const productSelect = wrapper.find('sw-entity-single-select-stub');

        expect(productSelect.exists()).toBeTruthy();
    });

    it('should show input select if item is custom item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            item: {
                ...wrapper.props().item,
                type: 'custom',
            },
        });

        const textField = wrapper.find('sw-text-field-stub');

        expect(textField.exists()).toBeTruthy();
    });

    it('should show input select if item is credit item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            item: {
                ...wrapper.props().item,
                type: 'credit',
            },
        });

        const textField = wrapper.find('sw-text-field-stub');

        expect(textField.exists()).toBeTruthy();
    });

    it('should show text if item is existing product', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            item: {
                ...wrapper.props().item,
                label: 'Existing product',
                type: 'product',
            },
        });

        const productSelect = wrapper.find('sw-entity-single-select-stub');
        const textField = wrapper.find('sw-text-field-stub');

        expect(productSelect.exists()).toBeFalsy();
        expect(textField.exists()).toBeFalsy();
        expect(wrapper.text()).toBe('Existing product');
    });

    it('product item should have net price if tax status is not gross', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            taxStatus: 'net',
            item: {
                ...wrapper.props().item,
                type: 'product',
            },
        });

        await wrapper.vm.onItemChanged('1');

        await flushPromises();

        expect(wrapper.vm.item.priceDefinition.price).toBe(100);
    });

    it('product item should have gross price if tax status is gross', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            taxStatus: 'gross',
            item: {
                ...wrapper.props().item,
                type: 'product',
            },
        });

        await wrapper.vm.onItemChanged('1');

        await flushPromises();

        expect(wrapper.vm.item.priceDefinition.price).toBe(110);
    });

    it('has correct criteria filters', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.productCriteria;

        expect(criteria.filters[0].type).toBe('multi');
        expect(criteria.filters[0].operator).toBe('OR');
        expect(criteria.filters[0].queries[0].type).toBe('equals');
        expect(criteria.filters[0].queries[0].field).toBe('childCount');
        expect(criteria.filters[0].queries[0].value).toBe(0);
        expect(criteria.filters[0].queries[1].type).toBe('equals');
        expect(criteria.filters[0].queries[1].field).toBe('childCount');
        expect(criteria.filters[0].queries[1].value).toBeNull();

        expect(criteria.filters[1].type).toBe('equals');
        expect(criteria.filters[1].field).toBe('visibilities.salesChannelId');
        expect(criteria.filters[1].value).toBe('1');
        expect(criteria.filters[2].type).toBe('equals');
        expect(criteria.filters[2].field).toBe('active');
        expect(criteria.filters[2].value).toBe(true);
    });
});
