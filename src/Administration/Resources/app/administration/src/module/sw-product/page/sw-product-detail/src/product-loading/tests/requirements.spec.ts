import { createPinia, setActivePinia } from 'pinia';
import {
    createDefaultFeatureSetCriteria,
    createProductCriteria,
    type LoadedProduct,
    type ProductLoadingDependencies,
    useProductLoading,
} from '../private';

/**
 * @sw-package inventory
 */

const store = () => Shopware.Store.get('swProductDetail');

/**
 * A product as the API returns it, with the given changes.
 */
function createProduct(changes: Partial<LoadedProduct> = {}): LoadedProduct {
    return { id: 'product-id', type: 'physical', purchasePrices: [], properties: {}, _origin: {}, ...changes };
}

/**
 * The dependencies of loading for a page that shows `product-id`, whose repositories answer with the given products.
 */
function createDependencies(
    products: Record<string, LoadedProduct | null>,
    changes: Partial<ProductLoadingDependencies> = {},
) {
    const search = <Result>(result: Result) => ({ search: jest.fn(() => Promise.resolve(result)) });
    const productRepository = { get: jest.fn((id: string) => Promise.resolve(products[id] ?? null)) };
    const propertyRepository = search({});
    const currencyRepository = search([{ id: 'euro', isSystemDefault: true }]);
    const taxRepository = search([]);
    const featureSetRepository = search([]);

    return {
        productId: () => 'product-id',
        productCriteria: createProductCriteria,
        defaultFeatureSetCriteria: createDefaultFeatureSetCriteria,
        productApiContext: () => ({}),
        productRepository: () => productRepository,
        propertyRepository: () => propertyRepository,
        currencyRepository: () => currencyRepository,
        taxRepository: () => taxRepository,
        featureSetRepository: () => featureSetRepository,
        customFieldDataProviderService: { getCustomFieldSets: () => Promise.resolve([]) },
        syncVariantPriceInheritance: jest.fn(),
        notifyError: jest.fn(),
        translate: (key: string) => key,
        navigate: jest.fn(() => Promise.resolve()),
        ...changes,
    } satisfies ProductLoadingDependencies;
}

// The product detail page loads the product it shows, and for a variant also its parent, into the product detail store.
// It loads the currencies, taxes and custom field sets the tabs need at the same time.
// Extensions replace single steps of the page, so every step loading takes over from the page stays replaceable.
describe('loading a product on the product detail page', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        store().$reset();
        jest.spyOn(Shopware.Telemetry, 'track').mockImplementation(() => {});
    });

    /**
     * @requirement A merchant who opens a product sees it with everything its tabs show.
     */
    it('loads the product and the shop data around it', async () => {
        // ARRANGE
        const deps = createDependencies({ 'product-id': createProduct() });

        // ACT
        await useProductLoading({}, deps).loadAll();

        // ASSERT
        expect(store().product).toMatchObject({ id: 'product-id' });
        expect(store().currencies).toEqual([{ id: 'euro', isSystemDefault: true }]);
        expect(store().isLoading).toBe(false);
    });

    /**
     * @requirement A merchant opens one product and sees its media, prices, SEO URLs and cross selling in the order
     * they arranged them, without a second request.
     */
    it('asks for the product with the associations its tabs show', () => {
        // ACT
        const request = createProductCriteria().parse() as {
            associations: Record<string, { sort?: unknown[]; filter?: unknown[] }>;
        };

        // ASSERT
        expect(Object.keys(request.associations)).toEqual(
            expect.arrayContaining([
                'media',
                'prices',
                'seoUrls',
                'crossSellings',
                'cover',
                'visibilities',
                'downloads',
            ]),
        );
        expect(request.associations.media.sort).toEqual([expect.objectContaining({ field: 'position' })]);
        expect(request.associations.seoUrls.filter).toEqual([
            expect.objectContaining({ field: 'isCanonical', value: true }),
        ]);
    });

    /**
     * @requirement A merchant editing a variant sees the properties assigned to the variant itself, sorted by name.
     */
    it('loads the properties assigned to the product itself', async () => {
        // ARRANGE
        const deps = createDependencies({ 'product-id': createProduct({ propertyIds: ['option-id'] }) });

        // ACT
        await useProductLoading({}, deps).loadProduct();

        // ASSERT
        const [[criteria]] = (deps.propertyRepository().search as jest.Mock).mock.calls as [[{ parse(): unknown }]];
        expect(criteria.parse()).toMatchObject({
            ids: 'option-id',
            sort: [expect.objectContaining({ field: 'name', order: 'ASC' })],
            filter: [expect.objectContaining({ field: 'productProperties.id', value: 'product-id' })],
        });
    });

    /**
     * @requirement A new product starts with the feature set the shop shipped with, even when merchants added more.
     */
    it('asks for the oldest feature set named Default or Standard', () => {
        // ACT
        const request = createDefaultFeatureSetCriteria().parse() as Record<string, unknown>;

        // ASSERT
        expect(request).toMatchObject({
            limit: 1,
            sort: [expect.objectContaining({ field: 'createdAt', order: 'ASC' })],
            filter: [expect.objectContaining({ type: 'equalsAny', field: 'name', value: 'Default|Standard' })],
        });
    });

    /**
     * @requirement A merchant sees a purchase price of 0 for a product that has none yet.
     */
    it('gives a product without purchase prices the default ones', async () => {
        // ARRANGE
        const deps = createDependencies({ 'product-id': createProduct() });

        // ACT
        await useProductLoading({}, deps).loadProduct();

        // ASSERT
        expect(store().product.purchasePrices).toEqual([{ currencyId: 'euro', net: 0, gross: 0, linked: true }]);
    });

    /**
     * @requirement A merchant editing a variant sees what it inherits from its parent.
     */
    it('loads the parent of a variant and lets the variant inherit from it', async () => {
        // ARRANGE
        const deps = createDependencies({
            'product-id': createProduct({ parentId: 'parent-id' }),
            'parent-id': createProduct({ id: 'parent-id' }),
        });

        // ACT
        await useProductLoading({}, deps).loadProduct();

        // ASSERT
        expect(store().parentProduct).toMatchObject({ id: 'parent-id' });
        expect(deps.syncVariantPriceInheritance).toHaveBeenCalledTimes(1);
    });

    /**
     * @requirement An extension that replaces one step of the page changes what loading the page does.
     */
    it('runs the steps the page passes instead of its own', async () => {
        // ARRANGE
        const deps = createDependencies({ 'product-id': createProduct({ parentId: 'parent-id' }) });
        const loadParentProduct = jest.fn(() => Promise.resolve());

        // ACT
        await useProductLoading({ loadParentProduct }, deps).loadProduct();

        // ASSERT
        expect(loadParentProduct).toHaveBeenCalledTimes(1);
        expect(deps.productRepository().get).toHaveBeenCalledTimes(1);
    });

    /**
     * @requirement A merchant who opens a deleted product learns that it is gone and returns to the product list.
     */
    it('leaves for the product list when the product does not exist', async () => {
        // ARRANGE
        const deps = createDependencies({});

        const loading = useProductLoading({}, deps);

        // ACT
        await loading.loadProduct();

        // ASSERT
        expect(deps.notifyError).toHaveBeenCalledWith({ message: 'sw-product.detail.messageProductNotFound' });
        expect(deps.navigate).toHaveBeenCalledWith({ name: 'sw.product.index' });
        expect(loading.isProductNotFound).toBe(true);
    });

    /**
     * @requirement The page stops showing its loading state also when the API fails.
     */
    it('ends the loading state of a request that fails', async () => {
        // ARRANGE
        const deps = createDependencies(
            {},
            { taxRepository: () => ({ search: () => Promise.reject(new Error('offline')) }) },
        );

        // ACT
        const loaded = useProductLoading({}, deps).loadTaxes();

        // ASSERT
        await expect(loaded).rejects.toThrow('offline');
        expect(store().isLoading).toBe(false);
    });
});
