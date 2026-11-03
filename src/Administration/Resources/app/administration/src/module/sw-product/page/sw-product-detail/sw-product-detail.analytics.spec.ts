/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';

type ProductDetailWrapper = VueWrapper<{
    product: { id: string; isNew: () => boolean };
    productRepository: { hasChanges: jest.Mock };
    syncRepository: { save: jest.Mock };
    saveProduct: () => Promise<unknown>;
    loadProduct: () => Promise<void>;
    savePreferenceUnits: () => Promise<void>;
    loadAll: () => Promise<unknown>;
}>;

async function createWrapper({ productType = 'digital', isNew = false } = {}) {
    const component = await wrapTestComponent('sw-product-detail', { sync: true });
    const product = {
        id: 'product-a',
        type: productType,
        purchasePrices: [{}],
        isNew: () => isNew,
    };
    const repository = {
        get: jest.fn().mockResolvedValue(product),
        save: jest.fn().mockResolvedValue(undefined),
        hasChanges: jest.fn().mockReturnValue(true),
    };

    const wrapper = shallowMount(component, {
        props: { productId: product.id, creationType: productType },
        global: {
            stubs: { 'sw-page': true },
            mocks: { $route: { name: 'sw.product.detail.base', params: { id: product.id } } },
            provide: {
                repositoryFactory: { create: () => repository },
                mediaService: {},
                numberRangeService: {},
                seoUrlService: {},
                systemConfigApiService: {},
                customFieldDataProviderService: {},
                entityValidationService: {},
            },
        },
    }) as unknown as ProductDetailWrapper;

    Object.assign(Shopware.Store.get('swProductDetail'), { product, creationType: productType });
    jest.spyOn(wrapper.vm, 'savePreferenceUnits').mockResolvedValue(undefined);
    jest.spyOn(wrapper.vm, 'loadAll').mockResolvedValue(undefined);

    return wrapper;
}

describe('module/sw-product/page/sw-product-detail analytics', () => {
    let trackSpy: jest.SpyInstance;
    let wrapper: ProductDetailWrapper;

    beforeAll(() => {
        Shopware.Component.override('sw-product-detail', {
            // Skip page initialization so each test controls when loading and saving happen.
            methods: { createdComponent() {} },
        });
    });

    beforeEach(() => {
        trackSpy = jest.spyOn(Shopware.Telemetry, 'track').mockImplementation(() => {});
        Shopware.Store.get('swProductDetail').$reset();
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    const productSaveCases = [
        { productType: 'digital', action: 'creation', isNew: true },
        { productType: 'digital', action: 'update', isNew: false },
        { productType: 'physical', action: 'creation', isNew: true },
        { productType: 'physical', action: 'update', isNew: false },
    ];

    it.each(productSaveCases)('tracks successful $productType product $action', async ({ productType, isNew }) => {
        wrapper = await createWrapper({ productType, isNew });
        // Saving changes the entity state; analytics must retain its original creation status.
        wrapper.vm.syncRepository.save.mockImplementation(() => {
            wrapper.vm.product.isNew = () => false;
            return Promise.resolve();
        });

        await wrapper.vm.saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'sw_product.detail.product_saved',
            product_type: productType,
            product_is_new: isNew,
        });
    });

    it('does not track unchanged products', async () => {
        wrapper = await createWrapper();
        wrapper.vm.productRepository.hasChanges.mockReturnValue(false);

        await wrapper.vm.saveProduct();

        expect(trackSpy).not.toHaveBeenCalled();
    });

    it('does not track failed saves', async () => {
        wrapper = await createWrapper();
        wrapper.vm.syncRepository.save.mockRejectedValue(new Error('Save failed'));

        await wrapper.vm.saveProduct();

        expect(trackSpy).not.toHaveBeenCalled();
    });

    it.each(['digital', 'physical'])('tracks opening a %s product', async (productType) => {
        wrapper = await createWrapper({ productType });

        await wrapper.vm.loadProduct();

        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'sw_product.detail.product_viewed',
            product_type: productType,
        });
    });

    it('does not repeat view events when the same product reloads', async () => {
        wrapper = await createWrapper();

        await wrapper.vm.loadProduct();
        await wrapper.vm.loadProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
    });
});
