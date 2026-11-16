/**
 * @sw-package discovery
 */
import { mount, RouterLinkStub, type VueWrapper } from '@vue/test-utils';
import type Criteria from 'src/core/data/criteria.data';

function createMedia(overrides: Record<string, unknown> = {}) {
    return {
        id: 'media',
        getEntityName: () => 'media',
        productMedia: [],
        productDocumentTranslations: [],
        categories: [],
        productManufacturers: [],
        mailTemplateMedia: [],
        documentBaseConfigs: [],
        avatarUsers: [],
        paymentMethods: [],
        shippingMethods: [],
        cmsBlocks: [],
        cmsSections: [],
        cmsPages: [],
        ...overrides,
    };
}

describe('sw-media-quickinfo-usage products', () => {
    let wrapper: VueWrapper;
    const product = { id: 'product', translated: { name: 'Operating manual product' } };
    const searchDocuments = jest.fn<Promise<ProductResult>, [Criteria]>();
    type ProductResult = (typeof product)[] & { total?: number };
    let documentRequests: ReturnType<Criteria['parse']>[];
    const modules = Shopware.Module.getModuleRegistry();
    const originalProductModule = modules.get('sw-product');

    beforeEach(() => {
        searchDocuments.mockReset().mockResolvedValue([]);
        documentRequests = [];
        modules.delete('sw-product');
        /** @private */
        Shopware.Module.register('sw-product', {
            type: 'core',
            name: 'product',
            title: 'Product',
            icon: 'regular-products',
            routes: { detail: { component: 'sw-product-detail', path: 'detail/:id' } },
        });
    });

    afterEach(() => {
        wrapper?.unmount();
        modules.delete('sw-product');
        if (originalProductModule) {
            modules.set('sw-product', originalProductModule);
        }
    });

    async function createWrapper(item = createMedia(), slotProducts: (typeof product)[] = []) {
        wrapper = mount(await wrapTestComponent('sw-media-quickinfo-usage', { sync: true }), {
            props: { item },
            global: {
                stubs: {
                    'router-link': RouterLinkStub,
                    'sw-loader': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (entity: string) => ({
                            search: (criteria: Criteria) => {
                                if (entity !== 'product') {
                                    return Promise.resolve([]);
                                }
                                if (
                                    criteria.filters.some(
                                        (filter) =>
                                            filter.type === 'equals' && filter.field === 'documents.translations.mediaId',
                                    )
                                ) {
                                    documentRequests.push(criteria.parse());
                                    return searchDocuments(criteria);
                                }
                                return Promise.resolve(slotProducts);
                            },
                        }),
                    },
                },
            },
        });
        await flushPromises();
        return wrapper;
    }

    it('finds document usages across all translations without embedded media assignments', async () => {
        searchDocuments.mockResolvedValue([product]);
        await createWrapper();

        expect(documentRequests[0].filter).toEqual([
            { type: 'equals', field: 'documents.translations.mediaId', value: 'media' },
        ]);
        expect(wrapper.getComponent(RouterLinkStub).props('to')).toEqual({
            name: 'sw.product.detail',
            params: { id: product.id },
        });
        expect(wrapper.text()).toContain(product.translated.name);
    });

    it('lists each product once across images, documents and CMS content without mutating the media', async () => {
        const otherProduct = { id: 'other-product', translated: { name: 'Other product' } };
        const productMedia = [{ productId: product.id, product }];
        const item = createMedia({ productMedia });
        searchDocuments.mockResolvedValue([product, otherProduct]);
        await createWrapper(item, [product, otherProduct]);

        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(2);
        expect(wrapper.text()).toContain(otherProduct.translated.name);
        expect(item.productMedia).toBe(productMedia);
        expect(item.productMedia).toHaveLength(1);
    });

    it('links the product returned by the document query instead of a product embedded in the media', async () => {
        const wrongProduct = { id: 'wrong-product', translated: { name: 'Wrong product' } };
        searchDocuments.mockResolvedValue([product]);
        await createWrapper(
            createMedia({
                productDocumentTranslations: [{ productDocument: { productId: product.id, product: wrongProduct } }],
            }),
        );

        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(1);
        expect(wrapper.getComponent(RouterLinkStub).props('to')).toEqual({
            name: 'sw.product.detail',
            params: { id: product.id },
        });
        expect(wrapper.text()).not.toContain(wrongProduct.translated.name);
    });

    it('loads document usages beyond the first 25 products', async () => {
        const firstPage = Array.from({ length: 25 }, (_, index) => ({
            id: `product-${index}`,
            translated: { name: `Product ${index}` },
        }));
        searchDocuments
            .mockResolvedValueOnce(Object.assign(firstPage, { total: 26 }))
            .mockResolvedValueOnce(Object.assign([product], { total: 26 }));
        await createWrapper();

        expect(documentRequests.map(({ page }) => page)).toEqual([1, 2]);
        expect(documentRequests[0].sort).toEqual([{ field: 'id', order: 'ASC', naturalSorting: false }]);
        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(26);
        expect(wrapper.text()).toContain(product.translated.name);
    });

    it('preserves loaded document and CMS usages if the next document page fails', async () => {
        const cmsProduct = { id: 'cms-product', translated: { name: 'CMS product' } };
        searchDocuments
            .mockResolvedValueOnce(Object.assign([product], { total: 2 }))
            .mockRejectedValueOnce(new Error('Next page unavailable'));
        await createWrapper(createMedia(), [cmsProduct]);

        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(2);
        expect(wrapper.text()).toContain(product.translated.name);
        expect(wrapper.text()).toContain(cmsProduct.translated.name);
        expect(wrapper.find('sw-loader-stub').exists()).toBe(false);
    });

    it('clears document usages when selecting another media file', async () => {
        searchDocuments.mockResolvedValueOnce([product]).mockResolvedValue([]);
        await createWrapper();
        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(1);

        await wrapper.setProps({ item: createMedia({ id: 'unused-media' }) });
        await flushPromises();

        expect(documentRequests[1].filter).toEqual([
            { type: 'equals', field: 'documents.translations.mediaId', value: 'unused-media' },
        ]);
        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(0);
        expect(wrapper.text()).not.toContain(product.translated.name);
    });

    it('preserves image and CMS usages when the document search fails', async () => {
        const cmsProduct = { id: 'cms-product', translated: { name: 'CMS product' } };
        searchDocuments.mockRejectedValue(new Error('Document usages unavailable'));
        await createWrapper(createMedia({ productMedia: [{ productId: product.id, product }] }), [cmsProduct]);

        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(2);
        expect(wrapper.text()).toContain(product.translated.name);
        expect(wrapper.text()).toContain(cmsProduct.translated.name);
        expect(wrapper.find('sw-loader-stub').exists()).toBe(false);
    });

    it('merges CMS and document usages after the document search finishes', async () => {
        const cmsProduct = { id: 'cms-product', translated: { name: 'CMS product' } };
        let resolveProducts!: (products: ProductResult) => void;
        const pending = new Promise<ProductResult>((resolve) => {
            resolveProducts = resolve;
        });
        searchDocuments.mockReturnValue(pending);
        await createWrapper(createMedia(), [cmsProduct]);
        expect(wrapper.find('sw-loader-stub').exists()).toBe(true);

        resolveProducts([product]);
        await flushPromises();

        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(2);
        expect(wrapper.text()).toContain(product.translated.name);
        expect(wrapper.text()).toContain(cmsProduct.translated.name);
        expect(wrapper.find('sw-loader-stub').exists()).toBe(false);
    });

    it('ignores document results for a previously selected media file', async () => {
        let resolveProducts!: (products: ProductResult) => void;
        const pending = new Promise<ProductResult>((resolve) => {
            resolveProducts = resolve;
        });
        searchDocuments.mockReturnValueOnce(pending).mockResolvedValue([]);
        await createWrapper();
        await wrapper.setProps({ item: createMedia({ id: 'unused-media' }) });
        await flushPromises();

        resolveProducts([product]);
        await flushPromises();

        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(0);
        expect(wrapper.find('sw-loader-stub').exists()).toBe(false);
    });
});
