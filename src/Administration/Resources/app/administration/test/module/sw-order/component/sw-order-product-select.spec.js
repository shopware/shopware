import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-product-select';

const createWrapper = () => {
    return shallowMount(Shopware.Component.build('sw-order-product-select'), {
        propsData: {
            item: {
                priceDefinition: {
                    isCalculated: false,
                    taxRules: [{ taxRate: 0, percentage: 100 }],
                    price: 0
                },
                price: {
                    taxRules: [{ taxRate: 0 }],
                    unitPrice: '...',
                    quantity: 1,
                    totalPrice: '...'
                },
                quantity: 1,
                unitPrice: 0,
                totalPrice: 0,
                precision: 2,
                label: ''
            },
            salesChannelId: '1'
        },
        stubs: {
            'sw-text-field': true,
            'sw-entity-single-select': true
        },
        mocks: {
            $tc: t => t
        }

    });
};

const repositoryMockFactory = () => {
    return {
        get: () => {
            return Promise.resolve({
                id: 1,
                name: 'Product test',
                price: [
                    {
                        gross: 110,
                        net: 100
                    }
                ],
                tax: {
                    taxRate: 7
                }
            });
        }
    };
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
                        PROMOTION: 'promotion'
                    });
                },

                getLineItemPriceTypes: () => {
                    return Object.freeze({
                        ABSOLUTE: 'absolute',
                        QUANTITY: 'quantity'
                    });
                }
            };
        });

        Shopware.Service().register('repositoryFactory', () => {
            return {
                create: () => repositoryMockFactory()
            };
        });
    });

    it('should show product select if item is new product', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            item: {
                ...wrapper.props().item,
                _isNew: true,
                type: 'product'
            }
        });

        const productSelect = wrapper.find('sw-entity-single-select-stub');

        expect(productSelect.exists()).toBeTruthy();
    });

    it('should show input select if item is custom item', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            item: {
                ...wrapper.props().item,
                type: 'custom'
            }
        });

        const textField = wrapper.find('sw-text-field-stub');

        expect(textField.exists()).toBeTruthy();
    });

    it('should show input select if item is credit item', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            item: {
                ...wrapper.props().item,
                type: 'credit'
            }
        });

        const textField = wrapper.find('sw-text-field-stub');

        expect(textField.exists()).toBeTruthy();
    });

    it('should show text if item is existing product', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            item: {
                ...wrapper.props().item,
                label: 'Existing product',
                type: 'product'
            }
        });

        const productSelect = wrapper.find('sw-entity-single-select-stub');
        const textField = wrapper.find('sw-text-field-stub');

        expect(productSelect.exists()).toBeFalsy();
        expect(textField.exists()).toBeFalsy();
        expect(wrapper.text()).toEqual('Existing product');
    });

    it('product item should have net price if tax status is not gross', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            taxStatus: 'net',
            item: {
                ...wrapper.props().item,
                type: 'product'
            }
        });

        await wrapper.vm.onItemChanged();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.item.priceDefinition.price).toEqual(100);
    });

    it('product item should have gross price if tax status is gross', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            taxStatus: 'gross',
            item: {
                ...wrapper.props().item,
                type: 'product'
            }
        });

        await wrapper.vm.onItemChanged();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.item.priceDefinition.price).toEqual(110);
    });
});
