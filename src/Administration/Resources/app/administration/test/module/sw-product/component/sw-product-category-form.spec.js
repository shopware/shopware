import { enableAutoDestroy, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/component/sw-product-category-form';
import 'src/app/component/utils/sw-inherit-wrapper';
import productStore from 'src/module/sw-product/page/sw-product-detail/state';

const { Utils } = Shopware;

enableAutoDestroy(afterEach);

describe('module/sw-product/component/sw-product-category-form', () => {
    function createWrapper(productEntityOverride, parentProductOverride) {
        const productEntity =
            {
                metaTitle: 'Product1',
                id: 'productId1',
                ...productEntityOverride
            };

        const parentProduct = {
            id: 'productId',
            ...parentProductOverride
        };

        return shallowMount(Shopware.Component.build('sw-product-category-form'), {
            mocks: {
                $tc: translationKey => translationKey,
                $route: {
                    name: 'sw.product.detail.base',
                    params: {
                        id: 1
                    }
                },
                $store: new Vuex.Store({
                    modules: {
                        swProductDetail: {
                            ...productStore,
                            state: {
                                ...productStore.state,
                                product: productEntity,
                                parentProduct,
                                loading: {
                                    product: false,
                                    media: false
                                },
                                advancedModeSetting: {
                                    value: {
                                        settings: [
                                            {
                                                key: 'visibility_structure',
                                                label: 'sw-product.detailBase.cardTitleVisibilityStructure',
                                                enabled: true,
                                                name: 'general'
                                            }
                                        ],
                                        advancedMode: {
                                            enabled: true,
                                            label: 'sw-product.general.textAdvancedMode'
                                        }
                                    }
                                }
                            }
                        }
                    }
                })
            },
            stubs: {
                'sw-container': {
                    template: '<div><slot></slot></div>'
                },
                'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper'),
                'sw-modal': true,
                'sw-multi-tag-select': true,
                'sw-switch-field': true,
                'sw-category-tree-field': true,
                'sw-entity-tag-select': true,
                'sw-product-visibility-select': true,
                'sw-help-text': true
            },
            provide: {
                repositoryFactory: {
                    create: () => {}
                }
            }
        });
    }

    let wrapper;

    it('should show Visibility Structure item fields when advanced mode is on', () => {
        wrapper = createWrapper();

        const structureFieldsClassName = [
            '.sw-product-category-form__tag-field-wrapper',
            '.sw-product-category-form__search-keyword-field'
        ];

        structureFieldsClassName.forEach(item => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
    });

    it('should hide Visibility Structure item fields when advanced mode is off', async () => {
        wrapper = createWrapper();
        const advancedModeSetting = Utils.get(wrapper, 'vm.$store.state.swProductDetail.advancedModeSetting');

        await wrapper.vm.$store.commit('swProductDetail/setAdvancedModeSetting', {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode'
                }
            }
        });

        const structureFieldsClassName = [
            '.sw-product-category-form__tag-field-wrapper',
            '.sw-product-category-form__search-keyword-field'
        ];

        structureFieldsClassName.forEach(item => {
            expect(wrapper.find(item).exists()).toBe(false);
        });
    });
});
