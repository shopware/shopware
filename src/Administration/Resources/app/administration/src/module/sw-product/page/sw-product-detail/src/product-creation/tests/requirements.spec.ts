import { createPinia, setActivePinia } from 'pinia';
import { type ProductCreationPage, useProductCreation } from '../private';

/**
 * @sw-package inventory
 */

const store = () => Shopware.Store.get('swProductDetail');

/**
 * A product detail page that creates a product of `creationType` in a shop with the given settings.
 */
function createPage(settings: Record<string, Record<string, unknown>> = {}, changes: Partial<ProductCreationPage> = {}) {
    return {
        creationStates: null,
        creationType: 'physical',
        defaultSalesChannelVisibility: 30,
        productRepository: { create: () => ({ id: 'new-id', visibilities: [], isNew: () => true }) },
        salesChannelRepository: {
            search: jest.fn(() => Promise.resolve([{ id: 'storefront-id' }])),
        },
        productVisibilityRepository: { create: () => ({}) },
        systemConfigApiService: { getValues: (domain: string) => Promise.resolve(settings[domain] ?? {}) },
        loadCurrencies: jest.fn(() => {
            store().currencies = [{ id: 'euro', isSystemDefault: true }] as never;
            return Promise.resolve();
        }),
        loadTaxes: jest.fn(() => Promise.resolve()),
        loadAttributeSet: jest.fn(() => Promise.resolve()),
        loadDefaultFeatureSet: jest.fn(() => Promise.resolve()),
        getDefaultPurchasePrices: () => [{ currencyId: 'euro', net: 0, gross: 0, linked: true }],
        ...changes,
    } as ProductCreationPage;
}

// A merchant who creates a product on the product detail page starts from a product with the shop's defaults.
// It is active and priced in the default currency, and it gets the tax rate and sales channels the shop settings name.
// Extensions replace single steps of the page, so every step creation takes over from the page stays replaceable.
describe('creating a product on the product detail page', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        store().$reset();
        jest.spyOn(Shopware.Service('cacheService'), 'query').mockImplementation(({ fn }) => fn());
    });

    /**
     * @requirement A merchant starts a new product that is active and priced in the default currency.
     */
    it('creates an active product with an empty price in the default currency', async () => {
        // ARRANGE
        const page = createPage();

        // ACT
        await useProductCreation({}, page).createState();

        // ASSERT
        expect(store().product).toMatchObject({
            active: true,
            type: 'physical',
            price: [{ currencyId: 'euro', net: null, gross: null, linked: true }],
            purchasePrices: [{ currencyId: 'euro', net: 0, gross: 0, linked: true }],
        });
        expect(store().localMode).toBe(true);
        expect(store().isLoading).toBe(false);
    });

    /**
     * @requirement A digital product sells once per order, because the customer downloads it.
     */
    it('lets a digital product sell once per order', async () => {
        // ARRANGE
        const page = createPage({}, { creationType: 'digital' });

        // ACT
        await useProductCreation({}, page).createState();

        // ASSERT
        expect(store().product).toMatchObject({ type: 'digital', maxPurchase: 1 });
    });

    /**
     * @requirement A merchant does not pick the tax rate and sales channels the shop settings already name.
     */
    it('applies the default tax rate and sales channels of the shop', async () => {
        // ARRANGE
        const page = createPage({
            'core.tax': { 'core.tax.defaultTaxRate': 'tax-id' },
            'core.defaultSalesChannel': {
                'core.defaultSalesChannel.salesChannel': ['storefront-id'],
                'core.defaultSalesChannel.visibility': { 'storefront-id': 10 },
                'core.defaultSalesChannel.active': false,
            },
        });

        // ACT
        await useProductCreation({}, page).createState();
        await flushPromises();

        // ASSERT
        expect(store().product).toMatchObject({
            taxId: 'tax-id',
            active: false,
            visibilities: [{ visibility: 10, salesChannelId: 'storefront-id', productId: 'new-id' }],
        });
    });

    /**
     * @requirement An extension that replaces one step of the page changes how a new product starts.
     */
    it('runs the steps the page passes instead of its own', async () => {
        // ARRANGE
        const page = createPage();
        const getDefaultTaxRate = jest.fn(() => Promise.resolve('extension-tax-id'));

        // ACT
        await useProductCreation({ getDefaultTaxRate }, page).createState();
        await flushPromises();

        // ASSERT
        expect(store().product).toMatchObject({ taxId: 'extension-tax-id' });
    });
});
