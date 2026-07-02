/**
 * @sw-package inventory
 */

import { mount, flushPromises } from '@vue/test-utils';

const { EntityCollection } = Shopware.Data;

// from Defaults.php
const HEADLESS_TYPE_ID = 'f183ee5650cf4bdb8a774337575067a6';
const STOREFRONT_TYPE_ID = '8a243080f92e4c719546314b577cf82b';
const PRODUCT_COMPARISON_TYPE_ID = 'ed535e5722134ac1aa6524f73e26881b';
const AGENTIC_COMMERCE_TYPE_ID = '5e29f9890c4d4d519a1c7f9d5c24b7c1';
const HEADLESS_SALES_CHANNEL_ID = 'headless-sales-channel-id';
const STOREFRONT_SALES_CHANNEL_ID = 'storefront-sales-channel-id';
const PRODUCT_COMPARISON_SALES_CHANNEL_ID = 'product-comparison-sales-channel-id';
const AGENTIC_COMMERCE_SALES_CHANNEL_ID = 'agentic-commerce-sales-channel-id';

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

function storefrontDefault(entityName, routeName) {
    return { id: `default-${entityName}`, routeName, entityName, salesChannelId: null, template: 't' };
}

// storefront default templates in their natural (product, landing page, category) order
const STOREFRONT_DEFAULTS = [
    storefrontDefault('product', 'frontend.detail.page'),
    storefrontDefault('landing_page', 'frontend.landing.page'),
    storefrontDefault('category', 'frontend.navigation.page'),
];

function headlessTemplateError(detail = 'raw backend message') {
    return { response: { data: { errors: [{ code: 'CONTENT__INVALID_HEADLESS_SEO_URL_TEMPLATE', detail }] } } };
}

async function createWrapper({ defaultTemplates = [], salesChannelTemplates = [] } = {}) {
    const seoUrlTemplateService = {
        preview: jest.fn().mockResolvedValue([]),
        getContext: jest.fn().mockResolvedValue({}),
    };

    const seoUrlService = {
        getStoreApiConfigs: jest.fn().mockResolvedValue([
            { routeName: 'store-api.product.detail', entityName: 'product', template: '' },
            { routeName: 'store-api.category.detail', entityName: 'category', template: '' },
            { routeName: 'store-api.landing-page.detail', entityName: 'landing_page', template: '' },
        ]),
    };

    const seoUrlTemplateRepository = {
        route: '/seo-url-template',
        schema: { entity: 'seo_url_template' },
        // first call (no sales channel) returns the storefront defaults, the next call returns the templates
        // already saved for the selected sales channel, every further call returns nothing
        search: jest
            .fn()
            .mockResolvedValueOnce(createSearchResult(defaultTemplates))
            .mockResolvedValueOnce(createSearchResult(salesChannelTemplates))
            .mockResolvedValue(createSearchResult([])),
        create: () => createSeoUrlTemplateEntity(),
        sync: jest.fn().mockResolvedValue(),
    };

    const salesChannelRepository = {
        search: jest.fn().mockResolvedValue([
            { id: STOREFRONT_SALES_CHANNEL_ID, typeId: STOREFRONT_TYPE_ID },
            { id: HEADLESS_SALES_CHANNEL_ID, typeId: HEADLESS_TYPE_ID },
            { id: PRODUCT_COMPARISON_SALES_CHANNEL_ID, typeId: PRODUCT_COMPARISON_TYPE_ID },
            { id: AGENTIC_COMMERCE_SALES_CHANNEL_ID, typeId: AGENTIC_COMMERCE_TYPE_ID },
        ]),
    };

    const wrapper = mount(await wrapTestComponent('sw-seo-url-template-card', { sync: true }), {
        global: {
            stubs: {
                'mt-card': { template: '<div class="mt-card"><slot name="toolbar" /><slot /></div>' },
                'mt-banner': true,
                'sw-sales-channel-switch': true,
                'sw-container': { template: '<div class="sw-container"><slot /></div>' },
                'sw-inherit-wrapper': true,
                'mt-text-field': true,
                'sw-single-select': true,
                'sw-loader': true,
                'mt-icon': true,
            },
            provide: {
                seoUrlTemplateService,
                seoUrlService,
                repositoryFactory: {
                    create: (entity) => (entity === 'sales_channel' ? salesChannelRepository : seoUrlTemplateRepository),
                },
            },
        },
    });

    await flushPromises();

    return { wrapper, seoUrlTemplateService, seoUrlTemplateRepository };
}

function routeNamesForSalesChannel(wrapper, salesChannelId) {
    return [...wrapper.vm.getTemplatesForSalesChannel(salesChannelId)].map((template) => template.routeName);
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
        expect(wrapper.vm.isHeadlessRoute('store-api.landing-page.detail')).toBe(true);
        expect(wrapper.vm.isHeadlessRoute('frontend.detail.page')).toBe(false);
    });

    it('should create and render store-api template fields without default values for a headless sales channel', async () => {
        const { wrapper, seoUrlTemplateService } = await createWrapper();

        await wrapper.vm.onSalesChannelChanged(HEADLESS_SALES_CHANNEL_ID);
        await flushPromises();

        const templates = wrapper.vm.getTemplatesForSalesChannel(HEADLESS_SALES_CHANNEL_ID);
        expect(templates.map((template) => template.routeName)).toEqual(
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
        expect(templates.find((template) => template.routeName === 'store-api.product.detail').entityName).toBe('product');

        // the fields are rendered instead of the "not supported" message, with preview + variable context requested
        expect(wrapper.findAll('.sw-seo-url-template-card__seo-url')).toHaveLength(3);
        expect(wrapper.text()).not.toContain('sw-seo-url.textSeoUrlsNotSupported');
        expect(seoUrlTemplateService.preview).toHaveBeenCalled();
        expect(seoUrlTemplateService.getContext).toHaveBeenCalled();
    });

    it('should not duplicate headless template entities on repeated creation', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.salesChannelId = HEADLESS_SALES_CHANNEL_ID;
        wrapper.vm.createSeoUrlTemplatesFromDefaultRoutes(HEADLESS_SALES_CHANNEL_ID);
        wrapper.vm.createSeoUrlTemplatesFromDefaultRoutes(HEADLESS_SALES_CHANNEL_ID);

        expect(wrapper.vm.getTemplatesForSalesChannel(HEADLESS_SALES_CHANNEL_ID)).toHaveLength(3);
    });

    it.each([
        [
            'storefront',
            STOREFRONT_SALES_CHANNEL_ID,
            true,
        ],
        [
            'headless',
            HEADLESS_SALES_CHANNEL_ID,
            true,
        ],
        [
            'product comparison',
            PRODUCT_COMPARISON_SALES_CHANNEL_ID,
            false,
        ],
        [
            'agentic commerce',
            AGENTIC_COMMERCE_SALES_CHANNEL_ID,
            false,
        ],
        [
            'none selected',
            null,
            true,
        ],
    ])('should determine SEO URL template support for a %s sales channel', async (_, salesChannelId, supported) => {
        const { wrapper } = await createWrapper();

        wrapper.vm.salesChannelId = salesChannelId;

        expect(wrapper.vm.salesChannelSupportsSeoUrlTemplates).toBe(supported);
    });

    it.each([
        [
            'product comparison',
            PRODUCT_COMPARISON_SALES_CHANNEL_ID,
        ],
        [
            'agentic commerce',
            AGENTIC_COMMERCE_SALES_CHANNEL_ID,
        ],
    ])(
        'should show the not-supported message instead of template fields for a %s sales channel',
        async (_, salesChannelId) => {
            const { wrapper } = await createWrapper();

            await wrapper.vm.onSalesChannelChanged(salesChannelId);
            await flushPromises();

            expect(wrapper.findAll('.sw-seo-url-template-card__seo-url')).toHaveLength(0);
            expect(wrapper.text()).toContain('sw-seo-url.textSeoUrlsNotSupported');
        },
    );

    it('should not provide an inherited placeholder for headless templates', async () => {
        const { wrapper } = await createWrapper();

        expect(
            wrapper.vm.getPlaceholder({ routeName: 'store-api.product.detail', salesChannelId: HEADLESS_SALES_CHANNEL_ID }),
        ).toBeNull();
    });

    it('should provide dedicated translated labels for headless store-api routes', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.getLabel({ routeName: 'store-api.product.detail' })).toBe(
            'sw-seo-url-template-card.routeNames.store-api-product-detail',
        );
        expect(wrapper.vm.getLabel({ routeName: 'store-api.landing-page.detail' })).toBe(
            'sw-seo-url-template-card.routeNames.store-api-landing-page-detail',
        );
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
        const syncedCollection = [...seoUrlTemplateRepository.sync.mock.calls[0][0]];

        // only the filled-in product template is persisted, no blank store-api.* / frontend.* rows
        expect(syncedCollection.map((entry) => entry.routeName)).toEqual(['store-api.product.detail']);
        syncedCollection.forEach((entry) => expect(entry.template).not.toBeNull());
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

    it('should append store-api routes that have no matching default template', async () => {
        const { wrapper } = await createWrapper({
            defaultTemplates: [storefrontDefault('product', 'frontend.detail.page')],
        });

        await wrapper.vm.onSalesChannelChanged(HEADLESS_SALES_CHANNEL_ID);
        await flushPromises();

        // product follows its matched default first, the unmatched store-api routes are appended
        expect(routeNamesForSalesChannel(wrapper, HEADLESS_SALES_CHANNEL_ID)).toEqual([
            'store-api.product.detail',
            'store-api.category.detail',
            'store-api.landing-page.detail',
        ]);
    });

    it('should keep the storefront default order and reuse a store-api template already saved for the channel', async () => {
        const { wrapper } = await createWrapper({
            defaultTemplates: STOREFRONT_DEFAULTS,
            // the product store-api template was already saved for this channel and is loaded from the DB
            salesChannelTemplates: [
                {
                    id: 'saved-product',
                    routeName: 'store-api.product.detail',
                    entityName: 'product',
                    salesChannelId: HEADLESS_SALES_CHANNEL_ID,
                    template: 'https://foo.bar/{{ product.id }}',
                },
            ],
        });

        await wrapper.vm.onSalesChannelChanged(HEADLESS_SALES_CHANNEL_ID);
        await flushPromises();

        const templates = [...wrapper.vm.getTemplatesForSalesChannel(HEADLESS_SALES_CHANNEL_ID)];

        // store-api routes replace their placeholder in the storefront default order, product not duplicated
        expect(templates.map((template) => template.routeName)).toEqual([
            'store-api.product.detail',
            'store-api.landing-page.detail',
            'store-api.category.detail',
        ]);
        // the saved template keeps its value (its entity was reused, not recreated)
        expect(templates.find((template) => template.routeName === 'store-api.product.detail').template).toBe(
            'https://foo.bar/{{ product.id }}',
        );
    });

    it('should still request preview and context for storefront default templates', async () => {
        const { seoUrlTemplateService } = await createWrapper({ defaultTemplates: STOREFRONT_DEFAULTS });

        await flushPromises();

        expect(seoUrlTemplateService.preview).toHaveBeenCalled();
        expect(seoUrlTemplateService.getContext).toHaveBeenCalled();
    });

    it('should translate the headless full-url error, keep it off the DAL field, and clear it once the preview succeeds', async () => {
        const { wrapper, seoUrlTemplateService } = await createWrapper();
        const entity = {
            id: 'headless-entity',
            routeName: 'store-api.product.detail',
            entityName: 'product',
            template: '{{ product.name }}',
        };

        seoUrlTemplateService.preview.mockRejectedValueOnce(headlessTemplateError());
        await wrapper.vm.fetchSeoUrlPreview(entity);
        await flushPromises();
        expect(wrapper.vm.errorMessages[entity.id]).toEqual({
            code: 'CONTENT__INVALID_HEADLESS_SEO_URL_TEMPLATE',
            detail: 'sw-seo-url-template-card.general.invalidHeadlessUrlTemplate',
        });
        expect(Shopware.Store.get('error').getApiErrorFromPath('seo_url_template', entity.id, ['template'])).toBeNull();

        seoUrlTemplateService.preview.mockResolvedValueOnce([{ seoPathInfo: 'https://foo.bar/test' }]);
        await wrapper.vm.fetchSeoUrlPreview(entity);
        await flushPromises();
        expect(wrapper.vm.errorMessages[entity.id]).toBeNull();
    });

    it('should keep the raw backend error for non-headless template errors', async () => {
        const { wrapper, seoUrlTemplateService } = await createWrapper();
        const error = { code: 'FRAMEWORK__INVALID_SEO_TEMPLATE', detail: 'Twig syntax error' };
        seoUrlTemplateService.preview.mockRejectedValue({ response: { data: { errors: [error] } } });

        const entity = {
            id: 'storefront-entity',
            routeName: 'frontend.detail.page',
            entityName: 'product',
            template: '{{ broken',
        };
        await wrapper.vm.fetchSeoUrlPreview(entity);
        await flushPromises();

        expect(wrapper.vm.errorMessages[entity.id]).toEqual(error);
    });
});
