/**
 * @sw-package discovery
 */
import { mount, RouterLinkStub, type VueWrapper } from '@vue/test-utils';

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
    const documentUsage = { productDocument: { productId: product.id, product } };
    const modules = Shopware.Module.getModuleRegistry();
    const originalProductModule = modules.get('sw-product');

    beforeEach(() => {
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

    async function createWrapper(
        item: ReturnType<typeof createMedia>,
        slotProducts: (typeof product)[] = [],
        inheritedProducts: Record<string, unknown>[] = [],
    ) {
        wrapper = mount(await wrapTestComponent('sw-media-quickinfo-usage', { sync: true }), {
            props: { item },
            global: {
                stubs: {
                    'router-link': RouterLinkStub,
                    'sw-loader': true,
                    'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info', { sync: true }),
                },
                provide: {
                    repositoryFactory: {
                        create: (entity: string) => ({
                            search: (criteria: { parse: () => { ids?: string[] } }, context?: { inheritance?: boolean }) =>
                                Promise.resolve(
                                    entity === 'product'
                                        ? context?.inheritance
                                            ? inheritedProducts.filter(({ id }) =>
                                                  criteria.parse().ids?.includes(id as string),
                                              )
                                            : slotProducts
                                        : [],
                                ),
                        }),
                    },
                },
            },
        });
        await flushPromises();
        return wrapper;
    }

    it('links a document file to the product that uses it', async () => {
        await createWrapper(createMedia({ productDocumentTranslations: [documentUsage] }));

        expect(wrapper.text()).toContain(product.translated.name);
        expect(wrapper.getComponent(RouterLinkStub).props('to')).toEqual({
            name: 'sw.product.detail',
            params: { id: product.id },
        });
    });

    it('lists each product once across document assignments and languages', async () => {
        const otherProduct = { id: 'other-product', translated: { name: 'Other product' } };
        const translations = [
            documentUsage,
            documentUsage,
            { productDocument: { productId: otherProduct.id, product: otherProduct } },
        ];
        const item = createMedia({ productDocumentTranslations: translations });
        await createWrapper(item);

        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(2);
        expect(wrapper.text()).toContain(product.translated.name);
        expect(wrapper.text()).toContain(otherProduct.translated.name);
        expect(item.productDocumentTranslations).toBe(translations);
        expect(item.productMedia).toHaveLength(0);
    });

    it('shows inherited product names and options for variants', async () => {
        const firstVariant = {
            id: 'variant-1',
            parentId: product.id,
            productNumber: 'SW-100-1',
            translated: { name: null },
            variation: [],
        };
        const secondVariant = {
            id: 'variant-2',
            parentId: product.id,
            productNumber: 'SW-100-2',
            translated: { name: null },
            variation: [],
        };
        await createWrapper(
            createMedia({
                productDocumentTranslations: [
                    documentUsage,
                    { productDocument: { productId: firstVariant.id, product: firstVariant } },
                    { productDocument: { productId: secondVariant.id, product: secondVariant } },
                ],
            }),
            [],
            [
                product,
                { ...secondVariant, translated: product.translated, variation: [{ group: 'Color', option: 'Blue' }] },
                { ...firstVariant, translated: product.translated, variation: [{ group: 'Color', option: 'Tan' }] },
            ],
        );

        const links = wrapper.findAllComponents(RouterLinkStub);
        expect(links).toHaveLength(3);
        expect(links[0].text()).toBe(product.translated.name);
        expect(links[1].text()).toContain(product.translated.name);
        expect(links[1].text()).toContain('Color: Tan');
        expect(links[2].text()).toContain(product.translated.name);
        expect(links[2].text()).toContain('Color: Blue');
        expect(wrapper.text()).not.toContain(firstVariant.productNumber);
        expect(wrapper.text()).not.toContain(secondVariant.productNumber);
    });

    it('does not duplicate a product also using the file as an image and in CMS content', async () => {
        const productMedia = [{ productId: product.id, product }];
        const item = createMedia({ productMedia, productDocumentTranslations: [documentUsage] });
        await createWrapper(item, [product]);

        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(1);
        expect(item.productMedia).toBe(productMedia);
        expect(productMedia).toHaveLength(1);
    });

    it('distinguishes a parent product and variant using the same image', async () => {
        const variant = {
            id: 'variant',
            parentId: product.id,
            productNumber: 'SW-100-1',
            translated: { name: null },
            variation: [],
        };
        await createWrapper(
            createMedia({
                productMedia: [
                    { productId: product.id, product },
                    { productId: variant.id, product: variant },
                ],
            }),
            [],
            [
                product,
                { ...variant, translated: product.translated, variation: [{ group: 'Color', option: 'Tan' }] },
            ],
        );

        const links = wrapper.findAllComponents(RouterLinkStub);
        expect(links).toHaveLength(2);
        expect(links[0].text()).toBe(product.translated.name);
        expect(links[1].text()).toContain(product.translated.name);
        expect(links[1].text()).toContain('Color: Tan');
        expect(links[1].props('to')).toEqual({ name: 'sw.product.detail', params: { id: variant.id } });
    });

    it('links the saved assignment owners when embedded products resolve to another variant', async () => {
        const imageOwner = { id: 'image-owner', translated: { name: 'Image owner' } };
        const documentOwner = { id: 'document-owner', translated: { name: 'Document owner' } };
        const wrongVariant = { id: 'wrong-variant', translated: { name: 'Wrong variant' } };
        await createWrapper(
            createMedia({
                productMedia: [{ productId: imageOwner.id, product: wrongVariant }],
                productDocumentTranslations: [
                    { productDocument: { productId: documentOwner.id, product: wrongVariant } },
                ],
            }),
            [],
            [imageOwner, documentOwner],
        );

        const links = wrapper.findAllComponents(RouterLinkStub);
        expect(links.map((link) => link.props('to'))).toEqual([
            { name: 'sw.product.detail', params: { id: imageOwner.id } },
            { name: 'sw.product.detail', params: { id: documentOwner.id } },
        ]);
        expect(wrapper.text()).toContain(imageOwner.translated.name);
        expect(wrapper.text()).toContain(documentOwner.translated.name);
        expect(wrapper.text()).not.toContain(wrongVariant.translated.name);
    });

    it('clears document usages when selecting another media file', async () => {
        await createWrapper(createMedia({ productDocumentTranslations: [documentUsage] }));
        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(1);

        await wrapper.setProps({ item: createMedia({ id: 'unused-media' }) });
        await flushPromises();
        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(0);
        expect(wrapper.text()).not.toContain(product.translated.name);
    });

    it('preserves image usages when document associations were not loaded', async () => {
        await createWrapper(
            createMedia({ productMedia: [{ productId: product.id, product }], productDocumentTranslations: undefined }),
        );

        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(1);
        expect(wrapper.text()).toContain(product.translated.name);
    });

    it('ignores document translations without a loaded product', async () => {
        await createWrapper(
            createMedia({
                productDocumentTranslations: [{ productDocument: null }, { productDocument: {} }],
            }),
        );

        expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(0);
    });
});
