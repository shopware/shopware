import { createPinia, setActivePinia } from 'pinia';
import { type ProductCreationDependencies, useProductCreation } from '../private';

/**
 * @sw-package inventory
 */

const store = () => Shopware.Store.get('swProductDetail');

/**
 * The dependencies of creation for a product of `creationType` in a shop with the given settings.
 */
function createDependencies(
    settings: Record<string, Record<string, unknown>> = {},
    changes: Partial<ProductCreationDependencies> = {},
) {
    return {
        creationStates: () => null,
        creationType: () => 'physical',
        productRepository: () => ({ create: () => ({ id: 'new-id', visibilities: [], isNew: () => true }) }),
        salesChannelRepository: () => ({ search: () => Promise.resolve([{ id: 'storefront-id' }]) }),
        productVisibilityRepository: () => ({ create: () => ({}) }),
        systemConfigApiService: { getValues: (domain: string) => Promise.resolve(settings[domain] ?? {}) },
        loadShopData: () => {
            store().currencies = [{ id: 'euro', isSystemDefault: true }] as never;
            return Promise.resolve();
        },
        getDefaultPurchasePrices: () => [{ currencyId: 'euro', net: 0, gross: 0, linked: true }],
        ...changes,
    } satisfies ProductCreationDependencies;
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
        const deps = createDependencies();

        // ACT
        await useProductCreation({}, deps).createState();

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
        const deps = createDependencies({}, { creationType: () => 'digital' });

        // ACT
        await useProductCreation({}, deps).createState();

        // ASSERT
        expect(store().product).toMatchObject({ type: 'digital', maxPurchase: 1 });
    });

    /**
     * @requirement A merchant does not pick the tax rate and sales channels the shop settings already name.
     */
    it('applies the default tax rate and sales channels of the shop', async () => {
        // ARRANGE
        const deps = createDependencies({
            'core.tax': { 'core.tax.defaultTaxRate': 'tax-id' },
            'core.defaultSalesChannel': {
                'core.defaultSalesChannel.salesChannel': ['storefront-id'],
                'core.defaultSalesChannel.visibility': { 'storefront-id': 10 },
                'core.defaultSalesChannel.active': false,
            },
        });

        // ACT
        await useProductCreation({}, deps).createState();
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
        const deps = createDependencies();
        const getDefaultTaxRate = jest.fn(() => Promise.resolve('extension-tax-id'));

        // ACT
        await useProductCreation({ getDefaultTaxRate }, deps).createState();
        await flushPromises();

        // ASSERT
        expect(store().product).toMatchObject({ taxId: 'extension-tax-id' });
    });
});
