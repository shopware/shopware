/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import TemplateFactory from 'src/core/factory/template.factory';
import { useProductionDeprecationGuard } from 'test/_helper_/deprecations';

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
                mocks: {
                    $te: () => false,
                },
                stubs: {
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-silence-deprecations': await wrapTestComponent('sw-silence-deprecations', { sync: true }),
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
                    'sw-list-price-field': await wrapTestComponent('sw-list-price-field', { sync: true }),
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
                    'sw-maintain-currencies-modal': {
                        name: 'sw-maintain-currencies-modal',
                        props: ['prices', 'disabled'],
                        emits: ['modal-close'],
                        template: '<button @click="$emit(\'modal-close\', prices)" />',
                    },
                    'mt-select': {
                        name: 'mt-select',
                        props: ['options'],
                        template: '<div />',
                    },
                },
            },
        });
    }

    /** @type Wrapper */
    let wrapper;
    let previousLocale;

    beforeEach(() => {
        previousLocale = Shopware.Store.get('session').currentLocale;
    });

    afterEach(() => {
        wrapper?.unmount();
        Shopware.Store.get('session').currentLocale = previousLocale;
        Shopware.Component.getOverrideRegistry().delete('sw-product-price-form');
        // Template overrides stay registered after the component override is removed.
        const registeredTemplate = TemplateFactory.getTemplateRegistry().get('sw-product-price-form');
        if (registeredTemplate) {
            registeredTemplate.overrides = [];
        }
        TemplateFactory.getNormalizedTemplateRegistry().delete('sw-product-price-form');
    });

    /* eslint-disable sw-test-rules/no-vm-access -- Deprecated extension callbacks require direct API coverage. */
    it.activeFeatureFlags(['v6.8.0.0'])('should reject legacy price APIs', async () => {
        useProductionDeprecationGuard();
        wrapper = await createWrapper();

        expect(() => wrapper.vm.removePriceInheritation()).toThrow(
            'sw-product-price-form.removePriceInheritation() is deprecated',
        );
        expect(() => wrapper.vm.inheritationCheckFunction()).toThrow(
            'sw-product-price-form.inheritationCheckFunction() is deprecated',
        );
        expect(() => wrapper.vm.prices).toThrow('sw-product-price-form.prices is deprecated');
        expect(() => wrapper.vm.parentPrices).toThrow('sw-product-price-form.parentPrices is deprecated');
    });
    /* eslint-enable sw-test-rules/no-vm-access */

    it.activeFeatureFlags(['v6.8.0.0'])('should show a localized tax rate percentage alongside each tax name', async () => {
        Shopware.Store.get('session').currentLocale = 'de-DE';
        wrapper = await createWrapper();
        await flushPromises();
        Shopware.Store.get('swProductDetail').taxes = [
            { id: 'standard', name: 'Standard rate', taxRate: 19 },
            { id: 'reduced', name: 'Reduced rate', taxRate: 7.5 },
            { id: 'zero', name: 'Zero rate', taxRate: 0 },
        ];
        await nextTick();

        const options = wrapper.findComponent({ name: 'mt-select' }).props('options');

        expect(options).toEqual([
            { id: 'standard', value: 'standard', label: 'Standard rate (19\u00A0%)' },
            { id: 'reduced', value: 'reduced', label: 'Reduced rate (7,5\u00A0%)' },
            { id: 'zero', value: 'zero', label: 'Zero rate (0\u00A0%)' },
        ]);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy price form branch.
    it.deprecated('v6.8.0.0')('should show the tax name without the tax rate percentage', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        Shopware.Store.get('swProductDetail').taxes = [
            { id: 'standard', name: 'Standard rate', taxRate: 19 },
        ];
        await nextTick();

        const options = wrapper.findComponent({ name: 'mt-select' }).props('options');

        expect(options).toEqual([
            { id: 'standard', value: 'standard', label: 'Standard rate' },
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should limit the percentage precision to three decimal places', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        Shopware.Store.get('swProductDetail').taxes = [
            { id: 'custom', name: 'Custom rate', taxRate: 7.12345 },
        ];
        await nextTick();

        const options = wrapper.findComponent({ name: 'mt-select' }).props('options');

        expect(options).toEqual([
            { id: 'custom', value: 'custom', label: 'Custom rate (7.123%)' },
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should toggle displayPrices inheritance without changing purchasePrices',
        async () => {
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
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])('should show inherited currency prices', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const currencyLink = wrapper.find('.sw-product-price-form__currency-link');
        expect(currencyLink.exists()).toBe(true);

        await currencyLink.trigger('click');

        const currencyModal = wrapper.findComponent({ name: 'sw-maintain-currencies-modal' });
        expect(currencyModal.props('prices')).toEqual(parentProductData.price);
        expect(currencyModal.props('disabled')).toBe(true);

        await currencyModal.trigger('click');

        expect(Shopware.Store.get('swProductDetail').product.price).toBeNull();
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should toggle purchasePrices inheritance without changing displayPrices',
        async () => {
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
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should use a valid zero price when purchase price inheritance is removed without a parent price',
        async () => {
            wrapper = await createWrapper();
            Shopware.Store.get('swProductDetail').parentProduct.purchasePrices = null;
            await flushPromises();

            const section = wrapper.find('.sw-product-price-form__purchase-prices');
            await section.find('.sw-inheritance-switch--is-inherited').trigger('click');

            expect(Shopware.Store.get('swProductDetail').product.purchasePrices).toEqual([
                {
                    currencyId: '1',
                    gross: 0,
                    net: 0,
                    linked: true,
                },
            ]);
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])('should show optional prices only in advanced mode', async () => {
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

    it.activeFeatureFlags(['v6.8.0.0'])('should render the replacement sections outside the deprecated blocks', async () => {
        Shopware.Component.override('sw-product-price-form', {
            template: `
                {% block sw_product_price_form_price_field %}{% endblock %}
                {% block sw_product_price_form_link %}{% endblock %}
            `,
        });

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-product-price-form__display-prices').exists()).toBe(true);
        expect(wrapper.find('.sw-product-price-form__currency-link').exists()).toBe(true);
        expect(wrapper.find('.sw-product-price-form__purchase-prices').exists()).toBe(true);
        expect(wrapper.find('.sw-product-price-form__price-list').exists()).toBe(false);
        expect(wrapper.find('.sw-product-price-form__links').exists()).toBe(false);
    });

    describe('legacy price form', () => {
        // @deprecated tag:v6.8.0 - The test will be removed with the legacy price form branch.
        it.deprecated('v6.8.0.0')('should render the combined price editor and its links', async () => {
            wrapper = await createWrapper({ price: structuredClone(parentProductData.price) });
            await flushPromises();

            expect(wrapper.find('.sw-product-price-form__price-list .sw-list-price-field__purchase-price').exists()).toBe(
                true,
            );
            expect(wrapper.find('.sw-product-price-form__links').exists()).toBe(true);
            expect(wrapper.find('.sw-product-price-form__display-prices').exists()).toBe(false);
            expect(wrapper.find('.sw-product-price-form__purchase-prices').exists()).toBe(false);
            expect(wrapper.find('.sw-product-price-form__currency-link').exists()).toBe(false);
        });

        // @deprecated tag:v6.8.0 - The test will be removed with the legacy price form branch.
        it.deprecated('v6.8.0.0')('should hide every price editor when the price field block is replaced', async () => {
            Shopware.Component.override('sw-product-price-form', {
                template: `
                    {% block sw_product_price_form_price_field %}
                        <div class="custom-price-field" />
                    {% endblock %}
                `,
            });

            wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.find('.custom-price-field').exists()).toBe(true);
            expect(wrapper.find('.sw-list-price-field').exists()).toBe(false);
            expect(wrapper.find('.sw-purchase-price-field').exists()).toBe(false);
        });

        // @deprecated tag:v6.8.0 - The test will be removed with the legacy price form branch.
        it.deprecated('v6.8.0.0')('should keep the links next to content appended to the link blocks', async () => {
            Shopware.Component.override('sw-product-price-form', {
                template: `
                    {% block sw_product_price_form_advanced_prices_link %}{% endblock %}

                    {% block sw_product_price_form_maintain_currencies_link %}
                        {% parent %}
                        <span class="custom-link" />
                    {% endblock %}
                `,
            });

            wrapper = await createWrapper({ price: structuredClone(parentProductData.price) });
            await flushPromises();

            const links = wrapper.find('.sw-product-price-form__links');
            expect(links.find('router-link-stub').exists()).toBe(false);
            expect(links.find('a.sw-card__quick-link').exists()).toBe(true);
            expect(links.find('.custom-link').exists()).toBe(true);
        });

        // @deprecated tag:v6.8.0 - The test will be removed with the legacy price form branch.
        it.deprecated('v6.8.0.0')('should call overridden inheritance callbacks', async () => {
            const removeInheritance = jest.fn(() => ({ price: [], purchasePrices: [] }));
            const checkInheritance = jest.fn(() => true);

            Shopware.Component.override('sw-product-price-form', {
                methods: {
                    removePriceInheritation: removeInheritance,
                    inheritationCheckFunction: checkInheritance,
                },
            });

            wrapper = await createWrapper();
            await flushPromises();

            expect(checkInheritance).toHaveBeenCalled();

            await wrapper.find('.sw-product-price-form__price-list .sw-inheritance-switch--is-inherited').trigger('click');

            expect(removeInheritance).toHaveBeenCalledTimes(1);
            expect(removeInheritance.mock.calls[0][0]).toEqual(expect.objectContaining({ price: parentProductData.price }));
        });
    });
});
