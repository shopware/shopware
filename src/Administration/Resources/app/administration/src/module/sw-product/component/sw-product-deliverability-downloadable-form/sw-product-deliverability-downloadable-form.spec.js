import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

/**
 * @sw-package inventory
 */

describe('module/sw-product/component/sw-product-deliverability-downloadable-form', () => {
    async function createWrapper(productEntityOverride, parentProductOverride) {
        const productEntity = {
            metaTitle: 'Product1',
            id: 'productId1',
            isCloseout: false,
            ...productEntityOverride,
        };

        const parentProduct = {
            id: 'productId',
            ...parentProductOverride,
        };

        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
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

        if (!Shopware.Feature.isActive('v6.8.0.0')) {
            store.creationStates = 'is-physical';
        }

        store.creationType = 'physical';

        return mount(await wrapTestComponent('sw-product-deliverability-downloadable-form', { sync: true }), {
            global: {
                mocks: {
                    $route: {
                        name: 'sw.product.detail.base',
                        params: {
                            id: 1,
                        },
                    },
                },
                provide: {
                    validationService: {},
                },
                stubs: {
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-entity-single-select': true,
                    'sw-inheritance-switch': true,
                    'sw-field-error': true,
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),

                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-help-text': true,
                    'sw-field-copyable': true,
                    'sw-ai-copilot-badge': true,
                },
            },
        });
    }

    let wrapper;

    const orderQuantityFieldsClassName = [
        '.product-deliverability-downloadable-form__min-purchase',
        '.product-deliverability-downloadable-form__purchase-steps',
        '.product-deliverability-downloadable-form__max-purchase',
    ];

    function orderQuantitySwitch() {
        return wrapper.find('input[name="sw-field--product-allow-multiple-units"]');
    }

    function expectOrderQuantityFields(visible) {
        orderQuantityFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBe(visible);
        });
    }

    it('should show Deliverability item fields when advanced mode is on', async () => {
        wrapper = await createWrapper({
            maxPurchase: 5,
        });
        await flushPromises();

        const deliveryFieldsClassName = [
            '.product-deliverability-downloadable-form__delivery-time',
            '.product-deliverability-downloadable-form__order-quantity-switch',
            ...orderQuantityFieldsClassName,
        ];

        deliveryFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
    });

    it('should show the delivery time above the stock and order quantity switches', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const deliveryTime = wrapper.find('.product-deliverability-downloadable-form__delivery-time').element;
        const stockSwitch = wrapper.find('.product-deliverability-downloadable-form__manage-stock-switch').element;
        const quantitySwitch = wrapper.find('.product-deliverability-downloadable-form__order-quantity-switch').element;

        expect(deliveryTime.compareDocumentPosition(stockSwitch) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(stockSwitch.compareDocumentPosition(quantitySwitch) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('should hide Deliverability item fields when advanced mode is off', async () => {
        wrapper = await createWrapper({
            maxPurchase: 5,
        });
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

        const deliveryFieldsClassName = [
            '.product-deliverability-downloadable-form__delivery-time',
            '.product-deliverability-downloadable-form__order-quantity-switch',
            ...orderQuantityFieldsClassName,
        ];

        await nextTick();

        deliveryFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBeFalsy();
        });
    });

    it('should hide the order quantities of a product limited to one unit per order', async () => {
        wrapper = await createWrapper({
            minPurchase: 1,
            purchaseSteps: 1,
            maxPurchase: 1,
        });
        await flushPromises();

        expect(orderQuantitySwitch().element.checked).toBe(false);
        expectOrderQuantityFields(false);
    });

    it.each([
        ['no max. order quantity', { maxPurchase: null }],
        ['a max. order quantity above 1', { maxPurchase: 5 }],
        ['a min. order quantity above 1', { minPurchase: 2, maxPurchase: 1 }],
        ['purchase steps above 1', { purchaseSteps: 2, maxPurchase: 1 }],
    ])('should show the order quantities of a product with %s', async (_, orderQuantities) => {
        wrapper = await createWrapper(orderQuantities, { id: null });
        await flushPromises();

        expect(orderQuantitySwitch().element.checked).toBe(true);
        expectOrderQuantityFields(true);
    });

    it.each([
        ['show', 5, true],
        ['hide', 1, false],
    ])(
        'should %s the order quantities of a variant whose parent has a max. order quantity of %d',
        async (_, parentMaxPurchase, expected) => {
            wrapper = await createWrapper(
                {
                    minPurchase: null,
                    purchaseSteps: null,
                    maxPurchase: null,
                },
                {
                    minPurchase: 1,
                    purchaseSteps: 1,
                    maxPurchase: parentMaxPurchase,
                },
            );
            await flushPromises();

            expect(orderQuantitySwitch().element.checked).toBe(expected);
            expectOrderQuantityFields(expected);
        },
    );

    it('should check the order quantities again once the parent of a variant is loaded', async () => {
        wrapper = await createWrapper({ maxPurchase: null }, { id: null });
        await flushPromises();

        expect(orderQuantitySwitch().element.checked).toBe(true);

        Shopware.Store.get('swProductDetail').parentProduct = {
            id: 'parentId',
            maxPurchase: 1,
        };
        await flushPromises();

        expect(orderQuantitySwitch().element.checked).toBe(false);
        expectOrderQuantityFields(false);
    });

    it('should lift the limit of one unit when the order quantity switch is turned on', async () => {
        wrapper = await createWrapper(
            {
                minPurchase: 1,
                purchaseSteps: 1,
                maxPurchase: 1,
            },
            { id: null },
        );
        await flushPromises();

        await orderQuantitySwitch().setChecked(true);

        expect(Shopware.Store.get('swProductDetail').product.maxPurchase).toBeNull();
        expectOrderQuantityFields(true);
    });

    it('should let a variant follow the max. order quantity of its parent when the order quantity switch is turned on', async () => {
        wrapper = await createWrapper({ maxPurchase: 1 }, { maxPurchase: 5 });
        await flushPromises();

        expect(orderQuantitySwitch().element.checked).toBe(false);

        await orderQuantitySwitch().setChecked(true);

        expect(Shopware.Store.get('swProductDetail').product.maxPurchase).toBeNull();
        expect(wrapper.find('.product-deliverability-downloadable-form__max-purchase input').element.value).toBe('5');
    });

    it('should limit the product to one unit per order when the order quantity switch is turned off', async () => {
        wrapper = await createWrapper(
            {
                minPurchase: 2,
                purchaseSteps: 2,
                maxPurchase: 10,
            },
            { id: null },
        );
        await flushPromises();

        await orderQuantitySwitch().setChecked(false);

        const { product } = Shopware.Store.get('swProductDetail');
        expect(product.minPurchase).toBe(1);
        expect(product.purchaseSteps).toBe(1);
        expect(product.maxPurchase).toBe(1);
        expectOrderQuantityFields(false);
    });

    it('should restore the order quantities when the order quantity switch is turned on again', async () => {
        wrapper = await createWrapper(
            {
                minPurchase: 2,
                purchaseSteps: 2,
                maxPurchase: 10,
            },
            { id: null },
        );
        await flushPromises();

        await orderQuantitySwitch().setChecked(false);
        await orderQuantitySwitch().setChecked(true);

        const { product } = Shopware.Store.get('swProductDetail');
        expect(product.minPurchase).toBe(2);
        expect(product.purchaseSteps).toBe(2);
        expect(product.maxPurchase).toBe(10);
    });

    it('should store a max purchase above one so customers can choose the quantity', async () => {
        wrapper = await createWrapper(
            {
                maxPurchase: 1,
            },
            { id: null },
        );
        await flushPromises();

        await orderQuantitySwitch().setChecked(true);

        const maxPurchaseInput = wrapper.find('.product-deliverability-downloadable-form__max-purchase input');
        await maxPurchaseInput.setValue('5');
        await maxPurchaseInput.trigger('change');

        expect(Shopware.Store.get('swProductDetail').product.maxPurchase).toBe(5);
    });

    it('should pre-fill stock value', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('input[name="sw-field--product-stock"]').element.value).toBe('0');
    });

    it('should set stock to before value if stock was not saved and isCloseout is set to false', async () => {
        wrapper = await createWrapper();
        await flushPromises();

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
            stock: 10,
        });
        await flushPromises();

        const isCloseoutSwitch = wrapper.find('input[name="sw-field--product-is-closeout"]');
        await isCloseoutSwitch.setChecked(true);

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
