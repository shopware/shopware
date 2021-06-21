import { enableAutoDestroy, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/component/sw-product-deliverability-form';
import 'src/app/component/utils/sw-inherit-wrapper';
import productStore from 'src/module/sw-product/page/sw-product-detail/state';

const { Utils } = Shopware;

enableAutoDestroy(afterEach);

describe('module/sw-product/component/sw-product-deliverability-form', () => {
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

        return shallowMount(Shopware.Component.build('sw-product-deliverability-form'), {
            mocks: {
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
                                                key: 'deliverability',
                                                label: 'sw-product.detailBase.cardTitleDeliverabilityInfo',
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
                            },
                            getters: {
                                ...productStore.getters,
                                isLoading: () => false
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
                'sw-field': true,
                'sw-entity-single-select': true,
                'sw-inheritance-switch': true
            }
        });
    }

    let wrapper;

    it('should show Deliverability item fields when advanced mode is on', () => {
        wrapper = createWrapper();

        const deliveryFieldsClassName = [
            '.product-deliverability-form__delivery-time',
            '.sw-product-deliverability__restock-field',
            '.sw-product-deliverability__shipping-free',
            '.sw-product-deliverability__min-purchase',
            '.sw-product-deliverability__purchase-step',
            '.sw-product-deliverability__max-purchase'
        ];

        deliveryFieldsClassName.forEach(item => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
    });

    it('should hide Deliverability item fields when advanced mode is off', async () => {
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

        const deliveryFieldsClassName = [
            '.product-deliverability-form__delivery-time',
            '.sw-product-deliverability__restock-field',
            '.sw-product-deliverability__shipping-free',
            '.sw-product-deliverability__min-purchase',
            '.sw-product-deliverability__purchase-step',
            '.sw-product-deliverability__max-purchase'
        ];

        deliveryFieldsClassName.forEach(item => {
            expect(wrapper.find(item).exists()).toBeFalsy();
        });
    });
});
