import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';
import swOrderAddItemsModal from 'src/module/sw-order/component/sw-order-add-items-modal';

Shopware.Component.register('sw-order-add-items-modal', swOrderAddItemsModal);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-order-add-items-modal'), {
        localVue,
        stubs: {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-tabs': await Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': await Shopware.Component.build('sw-tabs-item'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-order-product-grid': true,
            'sw-order-custom-item': true,
            'sw-order-credit-item': true,
            'sw-icon': true,
            'sw-loader': true,
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        create: () => {
                            return {
                                _isNew: true,
                            };
                        },
                    };
                },
            },
            cartStoreService: {
                getLineItemTypes: () => {
                    return {
                        PRODUCT: 'product',
                        CREDIT: 'credit',
                        CUSTOM: 'custom',
                        PROMOTION: 'promotion',
                    };
                },
                getLineItemPriceTypes: () => {
                    return {
                        ABSOLUTE: 'absolute',
                        QUANTITY: 'quantity',
                    };
                },
                addMultipleLineItems: () => {
                    return Promise.resolve();
                },
            },
            shortcutService: {
                stopEventListener: () => {},
                startEventListener: () => {},
            },
        },
        propsData: {
            currency: {},
            cart: {
                price: {
                    taxStatus: 'gross',
                },
            },
            salesChannelId: '',
        },
    });
}

describe('sw-order-add-items-modal', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.taxStatus).toBe('gross');
    });

    it('should be able to update products', async () => {
        wrapper.vm.onSelectProducts([{
            id: 'id',
            name: 'name',
        }]);

        expect(wrapper.vm.products).toEqual(expect.arrayContaining([
            expect.objectContaining({
                id: 'id',
                name: 'name',
            }),
        ]));
    });

    it('should be able to close modal', async () => {
        const buttonCancel = wrapper.find('.sw-order-add-items-modal__button-cancel');
        await buttonCancel.trigger('click');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should update items correctly', async () => {
        const addProductSpy = jest.spyOn(wrapper.vm, 'addProduct');
        const addCustomItemSpy = jest.spyOn(wrapper.vm, 'addCustomItem');
        const addCreditSpy = jest.spyOn(wrapper.vm, 'addCredit');

        await wrapper.setData({
            items: [],
            products: [
                {
                    id: '1',
                    name: 'Product',
                    amount: 5,
                    price: [
                        {
                            gross: 100,
                            net: 90,
                        },
                    ],
                    tax: {
                        taxRate: 0,
                    },
                },
            ],
            customItem: {
                label: 'Custom item',
                price: 100,
            },
            credit: {
                label: 'Credit',
                price: 100,
            },
        });

        const buttonSave = wrapper.find('.sw-order-add-items-modal__button-save');
        await buttonSave.trigger('click');

        // Assert products
        expect(addProductSpy).toHaveBeenCalledTimes(1);
        expect(addProductSpy).toHaveBeenCalledWith(expect.objectContaining({
            id: '1',
            name: 'Product',
            amount: 5,
        }));

        // Assert custom item
        expect(addCustomItemSpy).toHaveBeenCalledTimes(1);
        expect(addCustomItemSpy).toHaveBeenCalledWith(expect.objectContaining({
            label: 'Custom item',
            price: 100,
        }));

        // Assert credit
        expect(addCreditSpy).toHaveBeenCalledTimes(1);
        expect(addCreditSpy).toHaveBeenCalledWith(expect.objectContaining({
            label: 'Credit',
            price: 100,
        }));

        // Assert items
        expect(wrapper.vm.items).toEqual(expect.arrayContaining([
            expect.objectContaining({
                _isNew: true,
                type: 'product',
                label: 'Product',
            }),
            expect.objectContaining({
                _isNew: true,
                type: 'custom',
                label: 'Custom item',
            }),
            expect.objectContaining({
                _isNew: true,
                type: 'credit',
                label: 'Credit',
            }),
        ]));

        wrapper.vm.addProduct.mockClear();
        wrapper.vm.addCustomItem.mockClear();
        wrapper.vm.addCredit.mockClear();
    });

    it('should be able to add items', async () => {
        wrapper.vm.cartStoreService.addMultipleLineItems = jest.fn(() => {
            return Promise.resolve();
        });

        await wrapper.setData({
            items: [
                {
                    _isNew: true,
                    type: 'product',
                    label: 'Product',
                },
            ],
        });

        const buttonSave = wrapper.find('.sw-order-add-items-modal__button-save');
        await buttonSave.trigger('click');

        expect(wrapper.emitted('modal-save')).toBeTruthy();

        wrapper.vm.cartStoreService.addMultipleLineItems.mockRestore();
    });

    it('should not be able to add items', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.cartStoreService.addMultipleLineItems = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        await wrapper.setData({
            items: [
                {
                    _isNew: true,
                    type: 'product',
                    label: 'Product',
                },
            ],
        });

        const buttonSave = wrapper.find('.sw-order-add-items-modal__button-save');
        await buttonSave.trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith(expect.objectContaining({
            message: 'Whoops!',
        }));

        wrapper.vm.createNotificationError.mockRestore();
        wrapper.vm.cartStoreService.addMultipleLineItems.mockRestore();
    });
});
