/*
 * @package inventory
 */

import { shallowMount, createLocalVue, config } from '@vue/test-utils';
import VueRouter from 'vue-router';
import productStore from 'src/module/sw-product/page/sw-product-detail/state';
import swProductDetailContextPrices from 'src/module/sw-product/view/sw-product-detail-context-prices';
import 'src/app/component/base/sw-container';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/sw-list-price-field';
import 'src/app/component/form/sw-price-field';
import 'src/app/component/form/select/entity/sw-entity-single-select';

Shopware.Component.register('sw-product-detail-context-prices', swProductDetailContextPrices);

const { EntityCollection } = Shopware.Data;

const createWrapper = async () => {
    // delete global $router and $routes mocks
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();

    localVue.use(VueRouter);
    localVue.filter('asset', key => key);

    return shallowMount(await Shopware.Component.build('sw-product-detail-context-prices'), {
        localVue,
        stubs: {
            'sw-container': await Shopware.Component.build('sw-container'),
            'sw-card': true,
            'sw-icon': true,
            'sw-loader': true,
            'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-inheritance-switch': true,
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-button': true,
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-data-grid-settings': true,
            'sw-field': await Shopware.Component.build('sw-field'),
            'sw-number-field': await Shopware.Component.build('sw-number-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-list-price-field': await Shopware.Component.build('sw-list-price-field'),
            'sw-price-field': await Shopware.Component.build('sw-price-field'),
            'sw-entity-single-select': await Shopware.Component.build('sw-entity-single-select'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-skeleton': true,
            'sw-select-rule-create': true,
        },
        provide: {
            repositoryFactory: {
                create: (repositoryName) => {
                    if (repositoryName === 'rule') {
                        const rules = [
                            {
                                id: 'ruleId',
                                name: 'ruleName',
                            },
                        ];
                        rules.total = rules.length;

                        return {
                            search: () => Promise.resolve(rules),
                            get: () => Promise.resolve(rules),
                        };
                    }

                    if (repositoryName === 'product_price') {
                        return {
                            create: () => ({ search: () => Promise.resolve() }),
                        };
                    }

                    return {};
                },
            },
            validationService: {},
        },
    });
};

describe('src/module/sw-product/view/sw-product-detail-context-prices', () => {
    /** @type Wrapper */
    let wrapper;

    beforeEach(() => {
        if (Shopware.State.get('swProductDetail')) {
            Shopware.State.unregisterModule('swProductDetail');
        }
        Shopware.State.registerModule('swProductDetail', productStore);

        if (Shopware.State.get('context')) {
            Shopware.State.unregisterModule('context');
        }
        Shopware.State.registerModule('context', {
            namespaced: true,

            getters: {
                isSystemDefaultLanguage() {
                    return true;
                },
            },
        });
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should show inherited state when product is a variant', async () => {
        Shopware.State.commit('swProductDetail/setProduct', {
            id: 'productId',
            parentId: 'parentProductId',
            prices: [],
        });
        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: 'parentProductId',
        });

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBe(true);
        expect(wrapper.vm.isInherited).toBe(true);
    });

    it('should show empty state for main product', async () => {
        Shopware.State.commit('swProductDetail/setProduct', {
            id: 'productId',
            parentId: null,
            prices: [],
        });
        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: 'parentProductId',
        });

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBe(false);
        expect(wrapper.vm.isInherited).toBe(false);
    });

    it('first start quantity input should be disabled', async () => {
        Shopware.State.commit('swProductDetail/setProduct', {
            id: 'productId',
            parentId: 'parentProductId',
            prices: [
                {
                    ruleId: 'ruleId',
                    quantityStart: 1,
                    quantityEnd: 4,
                },
            ],
        });
        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: 'parentProductId',
        });

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        // get first quantity field
        const firstQuantityField = wrapper.find('.sw-data-grid__row--0 input[name="ruleId-1-quantityStart"]');

        // check if input field has a value of 1 and is disabled
        expect(firstQuantityField.element.value).toBe('1');
        expect(firstQuantityField.attributes('disabled')).toBe('disabled');
    });

    it('second start quantity input should not be disabled', async () => {
        global.activeAclRoles = ['product.editor'];

        Shopware.State.commit('swProductDetail/setProduct', {
            id: 'productId',
            parentId: null,
            prices: [
                {
                    ruleId: 'ruleId',
                    quantityStart: 1,
                    quantityEnd: 4,
                },
                {
                    ruleId: 'ruleId',
                    quantityStart: 5,
                    quantityEnd: null,
                },
            ],
        });
        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: 'parentProductId',
        });

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        // get second quantity field
        const secondQuantityField = wrapper.find('.sw-data-grid__row--1 input[name="ruleId-5-quantityStart"]');

        // check if input field has a value of 5 and is not disabled
        expect(secondQuantityField.element.value).toBe('5');
        expect(secondQuantityField.attributes('disabled')).toBeUndefined();
    });

    it('should show default price', async () => {
        const entities = [
            {
                ruleId: 'rule1',
                quantityStart: 1,
                quantityEnd: 4,
                price: [{
                    currencyId: 'euro',
                    gross: 1,
                    linked: false,
                    net: 1,
                    listPrice: null,
                }],
            },
        ];

        Shopware.State.commit('swProductDetail/setProduct', {
            id: 'productId',
            parentId: null,
            prices: new EntityCollection(
                '/test-price',
                'product_price',
                null,
                { isShopwareContext: true },
                entities,
                entities.length,
                null,
            ),
        });

        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: 'parentProductId',
        });

        Shopware.State.commit('swProductDetail/setCurrencies', [
            { id: 'euro', translated: { name: 'Euro' }, isSystemDefault: true, isoCode: 'EUR' },
        ]);

        wrapper = await createWrapper();
        const rulesEntities = [
            {
                id: 'rule1',
                name: 'customers',
            },
            {
                id: 'rule2',
                name: 'products',
            },
        ];

        await wrapper.setData({
            rules: new EntityCollection(
                '/test-rule',
                'rule',
                null,
                { isShopwareContext: true },
                rulesEntities,
                rulesEntities.length,
                null,
            ),
        });

        await wrapper.setProps({
            isSetDefaultPrice: true,
        });

        wrapper.vm.$parent.$el.children.item(0).scrollTo = () => {};

        await wrapper.vm.$nextTick();

        const firstPriceFieldGross = wrapper.find('.context-price-group-0 .sw-data-grid__row--0 .sw-data-grid__cell--price-EUR .sw-list-price-field__price input[name="sw-price-field-gross"]');
        expect(firstPriceFieldGross.element.value).toBe('1');

        await wrapper.vm.onAddNewPriceGroup('rule2');
        await flushPromises();

        const secondPriceFieldGross = wrapper.find('.context-price-group-1 .sw-data-grid__row--0 .sw-data-grid__cell--price-EUR .sw-list-price-field__price input[name="sw-price-field-gross"]');
        expect(secondPriceFieldGross.element.value).toBe('0');
    });
});
