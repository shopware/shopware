/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

function createMedia(overrides: Record<string, unknown> = {}) {
    return {
        id: 'media',
        fileName: 'manual.pdf',
        getEntityName: () => 'media',
        avatarUsers: [],
        categories: [],
        productMedia: [],
        productManufacturers: [],
        mailTemplateMedia: [],
        documentBaseConfigs: [],
        paymentMethods: [],
        shippingMethods: [],
        cmsBlocks: [],
        cmsSections: [],
        cmsPages: [],
        ...overrides,
    };
}

async function createWrapper(itemsToDelete: ReturnType<typeof createMedia>[]) {
    return mount(await wrapTestComponent('sw-media-modal-delete', { sync: true }), {
        props: { itemsToDelete },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-modal': true,
                'sw-media-quickinfo-usage': {
                    name: 'sw-media-quickinfo-usage',
                    props: ['item'],
                    template: '<div class="sw-media-quickinfo-usage" />',
                },
                'sw-media-media-item': {
                    name: 'sw-media-media-item',
                    props: ['item'],
                    template: '<div class="sw-media-media-item" />',
                },
            },
            provide: {
                repositoryFactory: { create: () => ({}) },
            },
        },
    });
}

describe('sw-media-modal-delete product document usage', () => {
    it('shows the usage warning and product list for one document file', async () => {
        const media = createMedia({ productDocumentTranslations: [{ productDocument: { productId: 'product' } }] });
        const wrapper = await createWrapper([media]);

        expect(wrapper.find('.sw-media-quickinfo-usage').exists()).toBe(true);
        expect(wrapper.getComponent({ name: 'sw-media-quickinfo-usage' }).props('item')).toEqual(media);
        expect(wrapper.text()).toContain('global.sw-media-modal-delete.mediaQuickInfoMessage');
        wrapper.unmount();
    });

    it('includes document files in the warning for a multiple-file deletion', async () => {
        const used = createMedia({ id: 'used', productDocumentTranslations: [{ productDocumentId: 'document' }] });
        const unused = createMedia({ id: 'unused' });
        const wrapper = await createWrapper([used, unused]);

        const warnedItems = wrapper.findAllComponents({ name: 'sw-media-media-item' });
        expect(warnedItems).toHaveLength(1);
        expect(warnedItems[0].props('item')).toEqual(used);
        expect(wrapper.text()).toContain('global.sw-media-modal-delete.mediaInUsagesMessage');
        wrapper.unmount();
    });
});
