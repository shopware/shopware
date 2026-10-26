/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

const parentProductData = {
    id: 'productId',
    purchasePrices: [
        {
            currencyId: '1',
            linked: true,
            gross: 0,
            net: 0,
        },
    ],
    price: [
        {
            currencyId: '1',
            linked: true,
            gross: 100,
            net: 84.034,
            listPrice: {
                currencyId: '1',
                linked: true,
                gross: 200,
                net: 168.07,
            },
            regulationPrice: {
                currencyId: '1',
                linked: true,
                gross: 100,
                net: 93.45,
            },
        },
    ],
};

describe('module/sw-product/component/sw-product-price-form', () => {
    async function createWrapper(productEntityOverride = {}) {
        const productEntity = {
            id: 'productId1',
            taxId: 'taxId',
            purchasePrices: null,
            price: null,
            ...productEntityOverride,
        };

        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = productEntity;
        store.parentProduct = structuredClone(parentProductData);
        store.advancedModeSetting = {
            value: {
                settings: [
                    {
                        key: 'prices',
                        label: 'sw-product.detailBase.cardTitlePrices',
                        enabled: true,
                        name: 'general',
                    },
                ],
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };
        store.currencies.push({
            id: '1',
            name: 'Euro',
            isoCode: 'EUR',
            isSystemDefault: true,
        });

        return mount(await wrapTestComponent('sw-product-price-form', { sync: true }), {
            global: {
                stubs: {
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-list-price-field': await wrapTestComponent('sw-list-price-field'),
                    'sw-inheritance-switch': {
                        props: ['isInherited', 'disabled'],
                        template: `
                            <button
                                :class="isInherited
                                    ? 'sw-inheritance-switch--is-inherited'
                                    : 'sw-inheritance-switch--is-not-inherited'"
                                :disabled="disabled"
                                @click="$emit(isInherited ? 'inheritance-remove' : 'inheritance-restore')"
                            />`,
                    },
                    'sw-price-field': true,
                    'sw-purchase-price-field': await wrapTestComponent('sw-purchase-price-field'),
                    'sw-help-text': true,
                    'sw-select-field': true,
                    'sw-internal-link': true,
                    'sw-maintain-currencies-modal': true,
                },
            },
        });
    }

    /** @type Wrapper */
    let wrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('should toggle displayPrices inheritance without changing purchasePrices', async () => {
        const purchasePrices = [{ currencyId: '1', gross: 60, net: 50, linked: true }];
        wrapper = await createWrapper({ purchasePrices: structuredClone(purchasePrices) });
        await flushPromises();

        const section = wrapper.find('.sw-product-price-form__display-prices');
        const field = section.find('.sw-list-price-field__price-field');
        expect(field.attributes('disabled')).toBeTruthy();
        const product = Shopware.Store.get('swProductDetail').product;

        await section.find('.sw-inheritance-switch--is-inherited').trigger('click');
        expect(field.attributes('disabled')).toBeFalsy();
        expect(product.price).toEqual(parentProductData.price);
        expect(product.purchasePrices).toEqual(purchasePrices);

        await section.find('.sw-inheritance-switch--is-not-inherited').trigger('click');
        expect(field.attributes('disabled')).toBeTruthy();
        expect(product.price).toBeNull();
        expect(product.purchasePrices).toEqual(purchasePrices);
    });

    it('should toggle purchasePrices inheritance without changing displayPrices', async () => {
        const displayPrices = [{ currencyId: '1', gross: 120, net: 100, linked: true }];
        wrapper = await createWrapper({ price: structuredClone(displayPrices) });
        await flushPromises();

        const section = wrapper.find('.sw-product-price-form__purchase-prices');
        const field = section.find('.sw-product-price-form__purchase-prices-field');
        expect(field.attributes('disabled')).toBe('true');
        const product = Shopware.Store.get('swProductDetail').product;

        await section.find('.sw-inheritance-switch--is-inherited').trigger('click');
        expect(field.attributes('disabled')).toBe('false');
        expect(product.purchasePrices).toEqual(parentProductData.purchasePrices);
        expect(product.price).toEqual(displayPrices);

        await section.find('.sw-inheritance-switch--is-not-inherited').trigger('click');
        expect(field.attributes('disabled')).toBe('true');
        expect(product.purchasePrices).toBeNull();
        expect(product.price).toEqual(displayPrices);
    });

    it('should show optional prices only in advanced mode', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-product-price-form__purchase-prices').exists()).toBe(true);
        expect(wrapper.find('.sw-list-price-field__list-price sw-price-field-stub').exists()).toBe(true);

        const advancedModeSetting = Shopware.Store.get('swProductDetail').advancedModeSetting;

        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };

        await nextTick();

        expect(wrapper.find('.sw-product-price-form__purchase-prices').exists()).toBe(false);

        expect(wrapper.find('.sw-list-price-field__list-price sw-price-field-stub').exists()).toBe(false);
    });
});
