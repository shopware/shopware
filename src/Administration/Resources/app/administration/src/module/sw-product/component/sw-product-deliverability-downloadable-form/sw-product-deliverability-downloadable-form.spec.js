import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

/**
 * @sw-package inventory
 */

describe('module/sw-product/component/sw-product-deliverability-downloadable-form', () => {
    // Like an entity, the product keeps the values it was loaded with as its origin
    function createProduct(values) {
        const origin = { ...values };

        return { ...values, getOrigin: () => origin };
    }

    async function createWrapper(productEntityOverride, parentProductOverride) {
        const productEntity = createProduct({
            metaTitle: 'Product1',
            id: 'productId1',
            isCloseout: false,
            ...productEntityOverride,
        });

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

    const NO_PARENT = { id: null };

    const orderQuantityFieldsClassName = [
        '.product-deliverability-downloadable-form__min-purchase',
        '.product-deliverability-downloadable-form__purchase-steps',
        '.product-deliverability-downloadable-form__max-purchase',
    ];

    const product = () => Shopware.Store.get('swProductDetail').product;
    const orderQuantity = () => {
        const { minPurchase, purchaseSteps, maxPurchase } = product();

        return { minPurchase, purchaseSteps, maxPurchase };
    };

    const orderQuantitySwitch = () => wrapper.find('input[name="sw-field--product-allow-multiple-units"]');
    const maxPurchaseInput = () => wrapper.find('.product-deliverability-downloadable-form__max-purchase input');
    const stockSwitch = () => wrapper.find('input[name="sw-field--product-is-closeout"]');
    const stockInput = () => wrapper.find('input[name="sw-field--product-stock"]');

    async function setMaxPurchase(value) {
        await maxPurchaseInput().setValue(value);
        await maxPurchaseInput().trigger('change');
    }

    function expectOrderQuantityFields(visible) {
        orderQuantityFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBe(visible);
        });
    }

    it('should show Deliverability item fields when advanced mode is on', async () => {
        wrapper = await createWrapper({ maxPurchase: 5 });
        await flushPromises();

        [
            '.product-deliverability-downloadable-form__delivery-time',
            '.product-deliverability-downloadable-form__order-quantity-switch',
            ...orderQuantityFieldsClassName,
        ].forEach((item) => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
    });

    it('should show the delivery time above the stock and order quantity switches', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const deliveryTime = wrapper.find('.product-deliverability-downloadable-form__delivery-time').element;
        const manageStock = wrapper.find('.product-deliverability-downloadable-form__manage-stock-switch').element;
        const allowMultipleUnits = wrapper.find('.product-deliverability-downloadable-form__order-quantity-switch').element;

        expect(deliveryTime.compareDocumentPosition(manageStock) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(manageStock.compareDocumentPosition(allowMultipleUnits) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('should hide Deliverability item fields when advanced mode is off', async () => {
        wrapper = await createWrapper({ maxPurchase: 5 });
        await flushPromises();

        const store = Shopware.Store.get('swProductDetail');
        store.advancedModeSetting = {
            value: {
                ...store.advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };
        await nextTick();

        [
            '.product-deliverability-downloadable-form__delivery-time',
            '.product-deliverability-downloadable-form__order-quantity-switch',
            ...orderQuantityFieldsClassName,
        ].forEach((item) => {
            expect(wrapper.find(item).exists()).toBe(false);
        });
    });

    it('should hide the order quantities of a product limited to one unit per order', async () => {
        wrapper = await createWrapper({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 1 });
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
        wrapper = await createWrapper(orderQuantities, NO_PARENT);
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
                { minPurchase: null, purchaseSteps: null, maxPurchase: null },
                { minPurchase: 1, purchaseSteps: 1, maxPurchase: parentMaxPurchase },
            );
            await flushPromises();

            expect(orderQuantitySwitch().element.checked).toBe(expected);
            expectOrderQuantityFields(expected);
        },
    );

    it('should keep the order quantities when the order quantity switch is turned on', async () => {
        wrapper = await createWrapper({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 1 }, NO_PARENT);
        await flushPromises();

        await orderQuantitySwitch().setChecked(true);

        expect(orderQuantity()).toEqual({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 1 });
        expectOrderQuantityFields(true);
    });

    it('should limit the product to one unit per order when the order quantity switch is turned off', async () => {
        wrapper = await createWrapper({ minPurchase: 2, purchaseSteps: 2, maxPurchase: 10 }, NO_PARENT);
        await flushPromises();

        await orderQuantitySwitch().setChecked(false);

        expect(orderQuantity()).toEqual({ minPurchase: 1, purchaseSteps: 1, maxPurchase: 1 });
        expectOrderQuantityFields(false);
    });

    it('should restore the order quantities when the order quantity switch is turned on again', async () => {
        wrapper = await createWrapper({ minPurchase: 2, purchaseSteps: 2, maxPurchase: 10 }, NO_PARENT);
        await flushPromises();

        await setMaxPurchase('20');
        expect(product().maxPurchase).toBe(20);

        await orderQuantitySwitch().setChecked(false);
        await orderQuantitySwitch().setChecked(true);

        expect(orderQuantity()).toEqual({ minPurchase: 2, purchaseSteps: 2, maxPurchase: 20 });
    });

    it('should store a max purchase above one so customers can choose the quantity', async () => {
        wrapper = await createWrapper({ maxPurchase: 1 }, NO_PARENT);
        await flushPromises();

        await orderQuantitySwitch().setChecked(true);
        await setMaxPurchase('5');

        expect(product().maxPurchase).toBe(5);
    });

    it('should pre-fill stock value', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(stockInput().element.value).toBe('0');
    });

    it('should set stock to before value if stock was not saved and isCloseout is set to false', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await stockSwitch().setChecked(true);
        await stockInput().setValue('5');
        await stockSwitch().setChecked(false);

        expect(stockInput().element.value).toBe('0');
    });

    it('should restore the entered stock when manage stock is turned on again', async () => {
        wrapper = await createWrapper({ stock: 10 });
        await flushPromises();

        await stockSwitch().setChecked(true);
        expect(stockInput().element.value).toBe('10');

        await stockInput().setValue('20');
        await stockSwitch().setChecked(false);
        expect(product().stock).toBe(10);

        await stockSwitch().setChecked(true);
        expect(stockInput().element.value).toBe('20');
    });

    it('should keep the entered stock when a variant inherits manage stock again', async () => {
        wrapper = await createWrapper({ isCloseout: true, stock: 10 }, { isCloseout: true });
        await flushPromises();

        await stockInput().setValue('20');
        await wrapper
            .findComponent('.product-deliverability-downloadable-form__manage-stock-switch')
            .vm.$emit('inheritance-restore');

        expect(product().isCloseout).toBeNull();
        expect(product().stock).toBe(20);
    });
});
