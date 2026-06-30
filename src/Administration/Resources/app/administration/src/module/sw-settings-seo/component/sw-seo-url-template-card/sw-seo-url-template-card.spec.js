/**
 * @sw-package inventory
 */

import { mount, flushPromises } from '@vue/test-utils';

const { EntityCollection } = Shopware.Data;

// from Defaults.php
const HEADLESS_TYPE_ID = 'f183ee5650cf4bdb8a774337575067a6';
const STOREFRONT_TYPE_ID = '8a243080f92e4c719546314b577cf82b';
const HEADLESS_SALES_CHANNEL_ID = 'headless-sales-channel-id';
const STOREFRONT_SALES_CHANNEL_ID = 'storefront-sales-channel-id';

let createdEntityCounter = 0;

function createSeoUrlTemplateEntity() {
    createdEntityCounter += 1;
    return { id: `seo-url-template-${createdEntityCounter}` };
}

function createSearchResult(items = []) {
    const collection = new EntityCollection('/seo-url-template', 'seo_url_template', Shopware.Context.api);
    items.forEach((item) => collection.add(item));
    return collection;
}

async function createWrapper({ defaultTemplates = [] } = {}) {
    const seoUrlTemplateService = {
        preview: jest.fn().mockResolvedValue([]),
        getContext: jest.fn().mockResolvedValue({}),
    };

    const seoUrlTemplateRepository = {
        route: '/seo-url-template',
        schema: { entity: 'seo_url_template' },
        // first call (no sales channel) returns the storefront defaults, every other call returns nothing
        search: jest
            .fn()
            .mockResolvedValueOnce(createSearchResult(defaultTemplates))
            .mockResolvedValue(createSearchResult([])),
        create: () => createSeoUrlTemplateEntity(),
        sync: jest.fn().mockResolvedValue(),
    };

    const salesChannelRepository = {
        search: jest.fn().mockResolvedValue([
            { id: STOREFRONT_SALES_CHANNEL_ID, typeId: STOREFRONT_TYPE_ID },
            { id: HEADLESS_SALES_CHANNEL_ID, typeId: HEADLESS_TYPE_ID },
        ]),
    };

    const wrapper = mount(await wrapTestComponent('sw-seo-url-template-card', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: '<div class="mt-card"><slot name="toolbar" /><slot /></div>',
                },
                'mt-banner': true,
                'sw-sales-channel-switch': true,
                'sw-container': {
                    template: '<div class="sw-container"><slot /></div>',
                },
                'sw-inherit-wrapper': true,
                'mt-text-field': true,
                'sw-single-select': true,
                'sw-loader': true,
                'mt-icon': true,
            },
            provide: {
                seoUrlTemplateService,
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'sales_channel') {
                            return salesChannelRepository;
                        }
                        return seoUrlTemplateRepository;
                    },
                },
            },
        },
    });

    await flushPromises();

    return { wrapper, seoUrlTemplateService, seoUrlTemplateRepository };
}

describe('src/module/sw-settings-seo/component/sw-seo-url-template-card', () => {
    beforeEach(() => {
        createdEntityCounter = 0;
    });

    it('should detect headless sales channels', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.salesChannelId = HEADLESS_SALES_CHANNEL_ID;
        expect(wrapper.vm.salesChannelIsHeadless).toBe(true);

        wrapper.vm.salesChannelId = STOREFRONT_SALES_CHANNEL_ID;
        expect(wrapper.vm.salesChannelIsHeadless).toBe(false);

        wrapper.vm.salesChannelId = null;
        expect(wrapper.vm.salesChannelIsHeadless).toBe(false);
    });

    it('should recognize headless store-api routes', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.isHeadlessRoute('store-api.product.detail')).toBe(true);
        expect(wrapper.vm.isHeadlessRoute('store-api.category.detail')).toBe(true);
        expect(wrapper.vm.isHeadlessRoute('store-api.landing-page.detail')).toBe(true);
        expect(wrapper.vm.isHeadlessRoute('frontend.detail.page')).toBe(false);
    });

    it('should create store-api template entities without default values for a headless sales channel', async () => {
        const { wrapper, seoUrlTemplateService } = await createWrapper();

        await wrapper.vm.onSalesChannelChanged(HEADLESS_SALES_CHANNEL_ID);
        await flushPromises();

        const templates = wrapper.vm.getTemplatesForSalesChannel(HEADLESS_SALES_CHANNEL_ID);
        const routeNames = templates.map((template) => template.routeName);

        expect(routeNames).toEqual(
            expect.arrayContaining([
                'store-api.product.detail',
                'store-api.category.detail',
                'store-api.landing-page.detail',
            ]),
        );
        expect(templates).toHaveLength(3);

        templates.forEach((template) => {
            expect(template.template).toBeNull();
            expect(template.salesChannelId).toBe(HEADLESS_SALES_CHANNEL_ID);
        });

        const productTemplate = templates.find((template) => template.routeName === 'store-api.product.detail');
        expect(productTemplate.entityName).toBe('product');

        // headless store-api routes are previewable and provide variable suggestions (resolved on the backend)
        expect(seoUrlTemplateService.preview).toHaveBeenCalled();
        expect(seoUrlTemplateService.getContext).toHaveBeenCalled();
    });

    it('should not duplicate headless template entities', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.salesChannelId = HEADLESS_SALES_CHANNEL_ID;
        wrapper.vm.createHeadlessSeoUrlTemplates(HEADLESS_SALES_CHANNEL_ID);
        wrapper.vm.createHeadlessSeoUrlTemplates(HEADLESS_SALES_CHANNEL_ID);

        expect(wrapper.vm.getTemplatesForSalesChannel(HEADLESS_SALES_CHANNEL_ID)).toHaveLength(3);
    });

    it('should render the template fields and no longer show the disallowed message for headless', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.vm.onSalesChannelChanged(HEADLESS_SALES_CHANNEL_ID);
        await flushPromises();

        expect(wrapper.findAll('.sw-seo-url-template-card__seo-url')).toHaveLength(3);
        expect(wrapper.text()).not.toContain('textSeoUrlsDisallowedForHeadless');
    });

    it('should not provide an inherited placeholder for headless templates', async () => {
        const { wrapper } = await createWrapper();

        const headlessTemplate = { routeName: 'store-api.product.detail', salesChannelId: HEADLESS_SALES_CHANNEL_ID };

        expect(wrapper.vm.getPlaceholder(headlessTemplate)).toBeNull();
    });

    it('should reuse the storefront label for headless store-api routes', async () => {
        const { wrapper } = await createWrapper();

        const productLabel = wrapper.vm.getLabel({ routeName: 'frontend.detail.page' });
        const headlessProductLabel = wrapper.vm.getLabel({ routeName: 'store-api.product.detail' });

        expect(headlessProductLabel).toBe(productLabel);
    });

    it('should only persist headless templates that have a value and never duplicate blank blueprint rows', async () => {
        const { wrapper, seoUrlTemplateRepository } = await createWrapper();

        await wrapper.vm.onSalesChannelChanged(HEADLESS_SALES_CHANNEL_ID);
        await flushPromises();

        // user only fills in the product template, the other two stay empty
        const productTemplate = wrapper.vm
            .getTemplatesForSalesChannel(HEADLESS_SALES_CHANNEL_ID)
            .find((template) => template.routeName === 'store-api.product.detail');
        productTemplate.template = 'https://foo.bar/product/{{ product.id }}';

        wrapper.vm.onClickSave();
        await flushPromises();

        expect(seoUrlTemplateRepository.sync).toHaveBeenCalledTimes(1);
        const syncedCollection = seoUrlTemplateRepository.sync.mock.calls[0][0];
        const syncedRouteNames = [...syncedCollection].map((entry) => entry.routeName);

        // only the filled-in product template is persisted, no blank store-api.* / frontend.* rows
        expect(syncedRouteNames).toEqual(['store-api.product.detail']);
        [...syncedCollection].forEach((entry) => {
            expect(entry.template).not.toBeNull();
        });
    });

    it('should emit the headless state when the sales channel changes', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.vm.onSalesChannelChanged(HEADLESS_SALES_CHANNEL_ID);
        await flushPromises();
        expect(wrapper.emitted('sales-channel-changed').at(-1)).toEqual([true]);

        await wrapper.vm.onSalesChannelChanged(STOREFRONT_SALES_CHANNEL_ID);
        await flushPromises();
        expect(wrapper.emitted('sales-channel-changed').at(-1)).toEqual([false]);
    });

    it('should keep a stable field order for headless templates regardless of insertion order', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.salesChannelId = HEADLESS_SALES_CHANNEL_ID;

        // add the headless entities in a shuffled order (mimicking arbitrary DB result order after saving)
        [
            'store-api.landing-page.detail',
            'store-api.product.detail',
            'store-api.category.detail',
        ].forEach((routeName) => {
            const entity = wrapper.vm.seoUrlTemplateRepository.create();
            entity.routeName = routeName;
            entity.salesChannelId = HEADLESS_SALES_CHANNEL_ID;
            entity.entityName = 'test';
            entity.template = null;
            wrapper.vm.seoUrlTemplates.add(entity);
        });

        const routeNames = wrapper.vm
            .getTemplatesForSalesChannel(HEADLESS_SALES_CHANNEL_ID)
            .map((template) => template.routeName);

        expect(routeNames).toEqual([
            'store-api.product.detail',
            'store-api.category.detail',
            'store-api.landing-page.detail',
        ]);
    });

    it('should still request preview and context for storefront templates', async () => {
        const { seoUrlTemplateService } = await createWrapper({
            defaultTemplates: [
                {
                    id: 'default-product-template',
                    routeName: 'frontend.detail.page',
                    entityName: 'product',
                    salesChannelId: null,
                    template: '{{ product.translated.name }}',
                },
            ],
        });

        await flushPromises();

        expect(seoUrlTemplateService.preview).toHaveBeenCalled();
        expect(seoUrlTemplateService.getContext).toHaveBeenCalled();
    });
});
