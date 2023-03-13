import { shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import swProductDeliverabilityDownloadForm from 'src/module/sw-product/component/sw-product-deliverability-downloadable-form';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import productStore from 'src/module/sw-product/page/sw-product-detail/state';

Shopware.Component.register('sw-product-deliverability-downloadable-form', swProductDeliverabilityDownloadForm);

const { Utils } = Shopware;
describe('module/sw-product/component/sw-product-deliverability-downloadable-form', () => {
    async function createWrapper(productEntityOverride, parentProductOverride) {
        const productEntity =
            {
                metaTitle: 'Product1',
                id: 'productId1',
                isCloseout: false,
                ...productEntityOverride
            };

        const parentProduct = {
            id: 'productId',
            ...parentProductOverride
        };

        return shallowMount(await Shopware.Component.build('sw-product-deliverability-downloadable-form'), {
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
                                },
                                creationStates: 'is-physical'
                            },
                            getters: {
                                ...productStore.getters,
                                isLoading: () => false
                            }
                        }
                    }
                })
            },
            provide: {
                validationService: {},
            },
            stubs: {
                'sw-container': {
                    template: '<div><slot></slot></div>'
                },
                'sw-inherit-wrapper': await Shopware.Component.build('sw-inherit-wrapper'),
                'sw-field': await Shopware.Component.build('sw-field'),
                'sw-entity-single-select': true,
                'sw-inheritance-switch': true,
                'sw-field-error': true,
                'sw-number-field': await Shopware.Component.build('sw-number-field'),
                'sw-text-field': await Shopware.Component.build('sw-text-field'),
                'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
                'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-help-text': true,
            }
        });
    }

    let wrapper;

    it('should show Deliverability item fields when advanced mode is on', async () => {
        wrapper = await createWrapper();

        const deliveryFieldsClassName = [
            '.product-deliverability-downloadable-form__delivery-time',
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
                    label: 'sw-product.general.textAdvancedMode'
                }
            }
        });

        const deliveryFieldsClassName = [
            '.product-deliverability-downloadable-form__delivery-time',
        ];

        deliveryFieldsClassName.forEach(item => {
            expect(wrapper.find(item).exists()).toBeFalsy();
        });
    });

    it('should pre-fill stock value', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('input[name="sw-field--product-stock"]').element.value).toBe('0');
    });

    it('should set stock to before value if stock was not saved and isCloseout is set to false', async () => {
        wrapper = await createWrapper();

        const isCloseoutSwitch = wrapper.find('input[name="sw-field--product-is-closeout"]');
        await isCloseoutSwitch.setChecked(true);

        const stockElement = wrapper.find('input[name="sw-field--product-stock"]');
        await stockElement.setValue('5');

        await isCloseoutSwitch.setChecked(false);
        await wrapper.vm.$nextTick();

        expect(stockElement.element.value).toBe('0');
    });

    it('should set stock to persisted product stock if stock was saved and stock deliverability menu is reopened', async () => {
        wrapper = await createWrapper({
            stock: 10
        });

        const isCloseoutSwitch = wrapper.find('input[name="sw-field--product-is-closeout"]');
        const stockElement = wrapper.find('input[name="sw-field--product-stock"]');

        expect(stockElement.element.value).toBe('10');

        await stockElement.setValue('20');
        expect(stockElement.element.value).toBe('20');

        await isCloseoutSwitch.setChecked(false);
        await wrapper.vm.$nextTick();

        await isCloseoutSwitch.setChecked(true);
        await wrapper.vm.$nextTick();

        expect(stockElement.element.value).toBe('10');
    });
});
