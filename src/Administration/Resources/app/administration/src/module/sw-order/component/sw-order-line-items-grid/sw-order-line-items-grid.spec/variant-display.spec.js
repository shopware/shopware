import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */
function createProductItem(overrides = {}) {
    return {
        id: '1',
        type: 'product',
        productId: 'product-id',
        label: 'Product item',
        quantity: 1,
        payload: {
            options: [
                { group: 'Color', option: 'Red' },
                { group: 'Size', option: 'L' },
            ],
            productNumber: 'product number',
        },
        priceDefinition: {
            price: 200,
        },
        price: {
            quantity: 1,
            totalPrice: 200,
            unitPrice: 200,
            calculatedTaxes: [{ price: 200, tax: 40, taxRate: 20 }],
            taxRules: [{ taxRate: 20, percentage: 100 }],
        },
        isNew: () => false,
        ...overrides,
    };
}

async function createWrapper(lineItems) {
    return mount(await wrapTestComponent('sw-order-line-items-grid', { sync: true }), {
        props: {
            order: {
                price: {
                    taxStatus: '',
                },
                currency: {
                    isoCode: 'EUR',
                },
                lineItems,
                taxStatus: '',
                itemRounding: {
                    decimals: 2,
                },
            },
            context: {
                authToken: {
                    access: 'token',
                },
            },
            isLoading: false,
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => ({ isNew: () => true, id: Shopware.Utils.createId() }),
                        delete: jest.fn(() => Promise.resolve()),
                    }),
                },
                orderService: {},
                feature: {
                    isActive: () => true,
                },
            },
            stubs: {
                'sw-container': await wrapTestComponent('sw-container', { sync: true }),
                'sw-button-group': true,
                'sw-context-button': true,
                'sw-context-menu-divider': true,
                'sw-context-menu-item': true,
                'sw-card-filter': true,
                'sw-checkbox-field': true,
                'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                'sw-data-grid-settings': true,
                'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info', { sync: true }),
                'router-link': {
                    template: '<a class="router-link" href="#"><slot></slot></a>',
                    props: ['to'],
                },
                'mt-number-field': true,
                'sw-order-product-select': true,
                'sw-modal': true,
                'sw-order-nested-line-items-modal': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'sw-data-grid-skeleton': true,
                'sw-base-field': true,
                'sw-field-error': true,
                'sw-highlight-text': true,
                'sw-provide': { template: '<slot/>', inheritAttrs: false },
            },
            mocks: {
                $t: (key) => key,
            },
            directives: {
                tooltip: {},
            },
        },
    });
}

describe('module/sw-order/component/sw-order-line-items-grid/variant-display', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('shows the variant characteristics of a product item linked to its product', async () => {
        const wrapper = await createWrapper([createProductItem()]);
        const label = wrapper.find('.sw-data-grid__row--0').find('.sw-data-grid__cell--label');

        expect(label.find('.router-link').exists()).toBe(true);

        const specifications = label.findAll('.sw-product-variant-info__specification');

        expect(specifications).toHaveLength(2);
        expect(specifications[0].text()).toContain('Color');
        expect(specifications[0].text()).toContain('Red');
        expect(specifications[1].text()).toContain('Size');
        expect(specifications[1].text()).toContain('L');
    });

    // sw-order-detail converts line items whose product was deleted into custom items on page
    // load, so this is the state the grid actually receives for a deleted product.
    it('still shows the variant characteristics of a converted product line item', async () => {
        const wrapper = await createWrapper([
            createProductItem({
                type: 'custom',
                productId: null,
                referencedId: null,
                payload: {
                    options: [
                        { group: 'Color', option: 'Red' },
                        { group: 'Size', option: 'L' },
                    ],
                    productNumber: 'product number',
                    isConvertedProductLineItem: true,
                },
            }),
        ]);
        const label = wrapper.find('.sw-data-grid__row--0').find('.sw-data-grid__cell--label');

        expect(label.find('.router-link').exists()).toBe(false);
        expect(label.find('.sw-order-line-items-grid__item-label').text()).toBe('Product item');

        const specifications = label.findAll('.sw-product-variant-info__specification');

        expect(specifications).toHaveLength(2);
        expect(specifications[0].text()).toContain('Color');
        expect(specifications[0].text()).toContain('Red');
        expect(specifications[1].text()).toContain('Size');
        expect(specifications[1].text()).toContain('L');
    });

    it('shows only the label for a custom item without variant options', async () => {
        const wrapper = await createWrapper([
            createProductItem({ type: 'custom', productId: null, referencedId: null, payload: [] }),
        ]);
        const label = wrapper.find('.sw-data-grid__row--0').find('.sw-data-grid__cell--label');

        expect(label.find('.sw-order-line-items-grid__item-label').text()).toBe('Product item');
        expect(label.find('.sw-product-variant-info').exists()).toBe(false);
    });

    it('shows only the label for a product item without variant options', async () => {
        const wrapper = await createWrapper([
            createProductItem({ payload: { productNumber: 'product number' } }),
        ]);
        const label = wrapper.find('.sw-data-grid__row--0').find('.sw-data-grid__cell--label');

        expect(label.find('.sw-order-line-items-grid__item-label').text()).toBe('Product item');
        expect(label.find('.sw-product-variant-info').exists()).toBe(false);
    });
});
