/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';

const parentLoadedByExtension = jest.fn(() => Promise.resolve());

/**
 * The product detail page of a variant, with an extension that replaces how the page loads the parent.
 */
async function mountVariantPage(): Promise<VueWrapper<{ loadProduct: () => Promise<void> }>> {
    const variant = { id: 'variant-id', parentId: 'parent-id', purchasePrices: [{}], properties: {}, _origin: {} };
    const repository = {
        get: jest.fn(() => Promise.resolve(variant)),
        search: jest.fn(() => Promise.resolve([])),
    };

    const wrapper = shallowMount(await wrapTestComponent('sw-product-detail', { sync: true }), {
        props: { productId: variant.id },
        global: {
            stubs: { 'sw-page': true },
            mocks: { $route: { name: 'sw.product.detail.base', params: { id: variant.id } } },
            provide: {
                repositoryFactory: { create: () => repository },
                mediaService: {},
                numberRangeService: {},
                seoUrlService: {},
                systemConfigApiService: {},
                customFieldDataProviderService: { getCustomFieldSets: () => Promise.resolve([]) },
                entityValidationService: {},
            },
        },
    });

    return wrapper as unknown as VueWrapper<{ loadProduct: () => Promise<void> }>;
}

describe('sw-product-detail public API', () => {
    beforeAll(() => {
        Shopware.Component.override('sw-product-detail', {
            methods: {
                // Skip page initialization, so the test decides when the product loads.
                async createdComponent() {},
                loadParentProduct: parentLoadedByExtension,
            },
        });
    });

    /** @public-api loadProduct, loadParentProduct */
    it('loads the parent of a variant through an override of loadParentProduct', async () => {
        // ARRANGE
        const wrapper = await mountVariantPage();

        // ACT
        await wrapper.vm.loadProduct();

        // ASSERT
        expect(parentLoadedByExtension).toHaveBeenCalledTimes(1);
    });
});
