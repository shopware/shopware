/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

describe('module/sw-product/component/sw-product-deliverability-form', () => {
    async function createWrapper(productEntityOverride, parentProductOverride) {
        const productEntity = {
            metaTitle: 'Product1',
            id: 'productId1',
            ...productEntityOverride,
        };

        const parentProduct = {
            id: 'productId',
            ...parentProductOverride,
        };

        const store = Shopware.Store.get('swProductDetail');
        store.product = productEntity;
        store.parentProduct = parentProduct;
        store.advancedModeSetting = {
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
        };
        store.creationStates = 'is-physical';

        return mount(
            await wrapTestComponent('sw-product-deliverability-form', {
                sync: true,
            }),
            {
                global: {
                    mocks: {
                        $route: {
                            name: 'sw.product.detail.base',
                            params: {
                                id: 1,
                            },
                        },
                    },
                    stubs: {
                        'sw-container': {
                            template: '<div><slot></slot></div>',
                        },
                        'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                        'sw-switch-field': true,
                        'sw-number-field': {
                            template: '<input class="sw-field">',
                        },
                        'sw-entity-single-select': true,
                        'sw-inheritance-switch': true,
                        'sw-help-text': true,
                    },
                },
            },
        );
    }

    let wrapper;

    it('should show Deliverability item fields when advanced mode is on', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const deliveryFieldsClassName = [
            '.product-deliverability-form__delivery-time',
            '.sw-product-deliverability__restock-field',
            '.sw-product-deliverability__shipping-free',
            '.sw-product-deliverability__min-purchase',
            '.sw-product-deliverability__purchase-step',
            '.sw-product-deliverability__max-purchase',
        ];

        deliveryFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
    });

    it('should hide Deliverability item fields when advanced mode is off', async () => {
        wrapper = await createWrapper();
        await flushPromises();

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

        const deliveryFieldsClassName = [
            '.product-deliverability-form__delivery-time',
            '.sw-product-deliverability__restock-field',
            '.sw-product-deliverability__shipping-free',
            '.sw-product-deliverability__min-purchase',
            '.sw-product-deliverability__purchase-step',
            '.sw-product-deliverability__max-purchase',
        ];

        deliveryFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBeFalsy();
        });
    });

    it('should pre-fill stock value', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-field[name="sw-field--product-stock"]').element.value).toBe('0');
    });
});
