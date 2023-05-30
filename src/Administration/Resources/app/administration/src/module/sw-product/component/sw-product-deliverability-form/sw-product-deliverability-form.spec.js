/*
 * @package inventory
 */

import { shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import swProductDeliverabilityForm from 'src/module/sw-product/component/sw-product-deliverability-form';
import 'src/app/component/utils/sw-inherit-wrapper';
import productStore from 'src/module/sw-product/page/sw-product-detail/state';

Shopware.Component.register('sw-product-deliverability-form', swProductDeliverabilityForm);

const { Utils } = Shopware;


describe('module/sw-product/component/sw-product-deliverability-form', () => {
    async function createWrapper(productEntityOverride, parentProductOverride) {
        const productEntity =
            {
                metaTitle: 'Product1',
                id: 'productId1',
                ...productEntityOverride,
            };

        const parentProduct = {
            id: 'productId',
            ...parentProductOverride,
        };

        return shallowMount(await Shopware.Component.build('sw-product-deliverability-form'), {
            mocks: {
                $route: {
                    name: 'sw.product.detail.base',
                    params: {
                        id: 1,
                    },
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
                                    media: false,
                                },
                                advancedModeSetting: {
                                    value: {
                                        settings: [
                                            {
                                                key: 'deliverability',
                                                label: 'sw-product.detailBase.cardTitleDeliverabilityInfo',
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
                                creationStates: 'is-physical',
                            },
                            getters: {
                                ...productStore.getters,
                                isLoading: () => false,
                            },
                        },
                    },
                }),
            },
            stubs: {
                'sw-container': {
                    template: '<div><slot></slot></div>',
                },
                'sw-inherit-wrapper': await Shopware.Component.build('sw-inherit-wrapper'),
                'sw-field': {
                    template: '<input class="sw-field">',
                },
                'sw-entity-single-select': true,
                'sw-inheritance-switch': true,
            },
        });
    }

    let wrapper;

    it('should show Deliverability item fields when advanced mode is on', async () => {
        wrapper = await createWrapper();

        const deliveryFieldsClassName = [
            '.product-deliverability-form__delivery-time',
            '.sw-product-deliverability__restock-field',
            '.sw-product-deliverability__shipping-free',
            '.sw-product-deliverability__min-purchase',
            '.sw-product-deliverability__purchase-step',
            '.sw-product-deliverability__max-purchase',
        ];

        deliveryFieldsClassName.forEach(item => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
    });

    it('should hide Deliverability item fields when advanced mode is off', async () => {
        wrapper = await createWrapper();
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

        const deliveryFieldsClassName = [
            '.product-deliverability-form__delivery-time',
            '.sw-product-deliverability__restock-field',
            '.sw-product-deliverability__shipping-free',
            '.sw-product-deliverability__min-purchase',
            '.sw-product-deliverability__purchase-step',
            '.sw-product-deliverability__max-purchase',
        ];

        deliveryFieldsClassName.forEach(item => {
            expect(wrapper.find(item).exists()).toBeFalsy();
        });
    });

    it('should pre-fill stock value', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('.sw-field[name="sw-field--product-stock"]').element.value).toBe('0');
    });
});
