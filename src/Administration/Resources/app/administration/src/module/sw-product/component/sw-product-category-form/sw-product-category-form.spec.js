/*
 * @package inventory
 */

import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import productStore from 'src/module/sw-product/page/sw-product-detail/state';

const { Utils } = Shopware;

describe('module/sw-product/component/sw-product-category-form', () => {
    const defaultSalesChannelData = {};

    async function createWrapper(productEntityOverride, parentProductOverride) {
        const productEntity =
            {
                metaTitle: 'Product1',
                id: 'productId1',
                isNew: () => false,
                ...productEntityOverride,
            };

        const parentProduct = {
            id: 'productId',
            ...parentProductOverride,
        };

        return mount(await wrapTestComponent('sw-product-category-form', { sync: true }), {
            global: {
                mocks: {
                    $route: {
                        name: 'sw.product.detail.base',
                        params: {
                            id: 1,
                        },
                    },
                    $store: createStore({
                        modules: {
                            swProductDetail: {
                                ...productStore,
                                state: {
                                    ...productStore.state,
                                    product: productEntity,
                                    parentProduct,
                                    loading: {
                                        product: false,
                                        media: false,
                                    },
                                    advancedModeSetting: {
                                        value: {
                                            settings: [
                                                {
                                                    key: 'visibility_structure',
                                                    label: 'sw-product.detailBase.cardTitleVisibilityStructure',
                                                    enabled: true,
                                                    name: 'general',
                                                },
                                            ],
                                            advancedMode: {
                                                enabled: true,
                                                label: 'sw-product.general.textAdvancedMode',
                                            },
                                        },
                                    },
                                },
                                creationStates: 'is-physical',
                            },
                        },
                    }),
                },
                stubs: {
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-modal': true,
                    'sw-multi-tag-select': true,
                    'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                    'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-category-tree-field': true,
                    'sw-entity-tag-select': true,
                    'sw-product-visibility-select': true,
                    'sw-help-text': true,
                    'sw-inheritance-switch': true,
                    'sw-icon': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve([{
                                    id: '98432def39fc4624b33213a56b8c944d',
                                    name: 'Headless',
                                }]),
                                create: () => ({}),
                            };
                        },
                    },
                    systemConfigApiService: {
                        getConfig: () => Promise.resolve(),
                        getValues: () => Promise.resolve(defaultSalesChannelData),
                    },
                    feature: {
                        isActive: () => true,
                    },
                },
            },
        });
    }

    let wrapper;

    it('should show Visibility Structure item fields when advanced mode is on', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const structureFieldsClassName = [
            '.sw-product-category-form__tag-field-wrapper',
            '.sw-product-category-form__search-keyword-field',
        ];

        structureFieldsClassName.forEach(item => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
    });

    it('should hide Visibility Structure item fields when advanced mode is off', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        const advancedModeSetting = Utils.get(wrapper, 'vm.$store.state.swProductDetail.advancedModeSetting');

        await wrapper.vm.$store.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        });

        const structureFieldsClassName = [
            '.sw-product-category-form__tag-field-wrapper',
            '.sw-product-category-form__search-keyword-field',
        ];

        structureFieldsClassName.forEach(item => {
            expect(wrapper.find(item).exists()).toBe(false);
        });
    });
});
