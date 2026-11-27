/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';

type ProductDetailWrapper = VueWrapper<{
    product: { id: string; active: boolean | null; isNew: () => boolean; getOrigin: () => { active: boolean | null } };
    productRepository: { hasChanges: jest.Mock };
    syncRepository: { save: jest.Mock };
    saveProduct: () => Promise<unknown>;
    loadProduct: () => Promise<void>;
    savePreferenceUnits: () => Promise<void>;
    loadAll: () => Promise<unknown>;
}>;

type CreateWrapperOptions = {
    productType?: string;
    isNew?: boolean;
    active?: boolean | null;
    originalActive?: boolean | null;
    parentActive?: boolean;
};

async function createWrapper({
    productType = 'digital',
    isNew = false,
    active = true,
    originalActive = active,
    parentActive,
}: CreateWrapperOptions = {}) {
    const component = await wrapTestComponent('sw-product-detail', { sync: true });
    const product = {
        id: 'product-a',
        type: productType,
        active,
        purchasePrices: [{}],
        isNew: () => isNew,
        getOrigin: () => ({ active: originalActive }),
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

    const vm = wrapper.vm;
    Object.assign(Shopware.Store.get('swProductDetail'), {
        product,
        parentProduct: { active: parentActive },
        creationType: productType,
    });
    jest.spyOn(vm, 'savePreferenceUnits').mockResolvedValue(undefined);
    jest.spyOn(vm, 'loadAll').mockResolvedValue(undefined);

    return {
        wrapper,
        product,
        repository,
        saveProduct: () => vm.saveProduct(),
        loadProduct: () => vm.loadProduct(),
    };
}

describe('module/sw-product/page/sw-product-detail analytics', () => {
    let trackSpy: jest.SpyInstance;
    let wrapper: ProductDetailWrapper;

    async function setup(options: CreateWrapperOptions = {}) {
        const context = await createWrapper(options);
        wrapper = context.wrapper;

        return context;
    }

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

    it('tracks successful creation of a digital product', async () => {
        const { product, repository, saveProduct } = await setup({ productType: 'digital', isNew: true });
        repository.save.mockImplementation(() => {
            product.isNew = () => false;
            return Promise.resolve();
        });

        await saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'sw_product.detail.product_saved',
            product_type: 'digital',
            product_is_new: true,
        });
    });

    it('tracks successful update of a digital product', async () => {
        const { saveProduct } = await setup({ productType: 'digital' });

        await saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'sw_product.detail.product_saved',
            product_type: 'digital',
            product_is_new: false,
        });
    });

    it('tracks successful creation of a physical product', async () => {
        const { product, repository, saveProduct } = await setup({ productType: 'physical', isNew: true });
        repository.save.mockImplementation(() => {
            product.isNew = () => false;
            return Promise.resolve();
        });

        await saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'sw_product.detail.product_saved',
            product_type: 'physical',
            product_is_new: true,
        });
    });

    it('tracks successful update of a physical product', async () => {
        const { saveProduct } = await setup({ productType: 'physical' });

        await saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'sw_product.detail.product_saved',
            product_type: 'physical',
            product_is_new: false,
        });
    });

    it('does not track unchanged products', async () => {
        const { repository, saveProduct } = await setup();
        repository.hasChanges.mockReturnValue(false);

        await saveProduct();

        expect(trackSpy).not.toHaveBeenCalled();
    });

    it('does not track failed saves', async () => {
        const { repository, saveProduct } = await setup({ active: true, originalActive: false });
        repository.save.mockRejectedValue(new Error('Save failed'));

        await saveProduct();

        expect(trackSpy).not.toHaveBeenCalled();
    });

    it('tracks successful activation of a digital product', async () => {
        const { saveProduct } = await setup({ productType: 'digital', active: true, originalActive: false });

        await saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(2);
        expect(trackSpy).toHaveBeenNthCalledWith(2, {
            eventName: 'sw_product.detail.product_activated',
            product_type: 'digital',
        });
    });

    it('tracks successful activation of a physical product', async () => {
        const { saveProduct } = await setup({ productType: 'physical', active: true, originalActive: false });

        await saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(2);
        expect(trackSpy).toHaveBeenNthCalledWith(2, {
            eventName: 'sw_product.detail.product_activated',
            product_type: 'physical',
        });
    });

    it('tracks activation when a variant overrides an inactive parent', async () => {
        const { saveProduct } = await setup({ active: true, originalActive: null, parentActive: false });

        await saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(2);
        expect(trackSpy).toHaveBeenNthCalledWith(2, {
            eventName: 'sw_product.detail.product_activated',
            product_type: 'digital',
        });
    });

    it('tracks activation when a variant starts inheriting from an active parent', async () => {
        const { saveProduct } = await setup({ active: null, originalActive: false, parentActive: true });

        await saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(2);
        expect(trackSpy).toHaveBeenNthCalledWith(2, {
            eventName: 'sw_product.detail.product_activated',
            product_type: 'digital',
        });
    });

    it('does not track activation when a product remains active', async () => {
        const { saveProduct } = await setup({ active: true, originalActive: true });

        await saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
        expect(trackSpy).not.toHaveBeenCalledWith(
            expect.objectContaining({ eventName: 'sw_product.detail.product_activated' }),
        );
    });

    it('does not track activation when a product remains inactive', async () => {
        const { saveProduct } = await setup({ active: false, originalActive: false });

        await saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
        expect(trackSpy).not.toHaveBeenCalledWith(
            expect.objectContaining({ eventName: 'sw_product.detail.product_activated' }),
        );
    });

    it('does not track activation when a product is deactivated', async () => {
        const { saveProduct } = await setup({ active: false, originalActive: true });

        await saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
        expect(trackSpy).not.toHaveBeenCalledWith(
            expect.objectContaining({ eventName: 'sw_product.detail.product_activated' }),
        );
    });

    it('does not track activation when a product is created active', async () => {
        const { saveProduct } = await setup({ active: true, originalActive: false, isNew: true });

        await saveProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
        expect(trackSpy).not.toHaveBeenCalledWith(
            expect.objectContaining({ eventName: 'sw_product.detail.product_activated' }),
        );
    });

    it('tracks opening a digital product', async () => {
        const { loadProduct } = await setup({ productType: 'digital' });

        await loadProduct();

        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'sw_product.detail.product_viewed',
            product_type: 'digital',
        });
    });

    it('tracks opening a physical product', async () => {
        const { loadProduct } = await setup({ productType: 'physical' });

        await loadProduct();

        expect(trackSpy).toHaveBeenCalledWith({
            eventName: 'sw_product.detail.product_viewed',
            product_type: 'physical',
        });
    });

    it('does not repeat view events when the same product reloads', async () => {
        const { loadProduct } = await setup();

        await loadProduct();
        await loadProduct();

        expect(trackSpy).toHaveBeenCalledTimes(1);
    });
});
