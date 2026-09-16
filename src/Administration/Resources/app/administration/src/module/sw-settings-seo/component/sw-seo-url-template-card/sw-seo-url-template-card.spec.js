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
const HEADLESS_NO_DOMAIN_SALES_CHANNEL_ID = 'headless-no-domain-sales-channel-id';
const STOREFRONT_SALES_CHANNEL_ID = 'storefront-sales-channel-id';
const PRODUCT_COMPARISON_SALES_CHANNEL_ID = 'product-comparison-sales-channel-id';
const AGENTIC_COMMERCE_SALES_CHANNEL_ID = 'agentic-commerce-sales-channel-id';

let createdEntityCounter = 0;

// minimal entity stub: getOrigin() returns a mutable snapshot, mirroring the DAL change-tracking API
function entityStub(data) {
    return { ...data, getOrigin: () => ({}) };
}

function createSeoUrlTemplateEntity() {
    createdEntityCounter += 1;
    return entityStub({ id: `seo-url-template-${createdEntityCounter}` });
}

function createSearchResult(items = []) {
    const collection = new EntityCollection('/seo-url-template', 'seo_url_template', Shopware.Context.api);
    items.forEach((item) => collection.add(item));
    return collection;
}

function storefrontDefault(entityName, routeName, template = 't') {
    return entityStub({
        id: `default-${entityName}`,
        routeName,
        entityName,
        salesChannelId: null,
        template,
        isHeadless: false,
    });
}

function headlessDefault(entityName, routeName, template = 't') {
    return entityStub({
        id: `default-${entityName}-headless`,
        routeName,
        entityName,
        salesChannelId: null,
        template,
        isHeadless: true,
    });
}

// storefront default templates in their natural (product, landing page, category) order
const STOREFRONT_DEFAULTS = [
    storefrontDefault('product', 'frontend.detail.page'),
    storefrontDefault('landing_page', 'frontend.landing.page'),
    storefrontDefault('category', 'frontend.navigation.page'),
];

// the matching store-api (headless) defaults, seeded by the migration, loaded alongside the storefront ones
const HEADLESS_DEFAULTS = [
    headlessDefault('product', 'store-api.product.detail'),
    headlessDefault('landing_page', 'store-api.landing-page.detail'),
    headlessDefault('category', 'store-api.category.detail'),
];

const ALL_DEFAULTS = [
    ...STOREFRONT_DEFAULTS,
    ...HEADLESS_DEFAULTS,
];

async function createWrapper({ defaultTemplates = ALL_DEFAULTS, salesChannelTemplates = [] } = {}) {
    const seoUrlTemplateService = {
        preview: jest.fn().mockResolvedValue([]),
        getContext: jest.fn().mockResolvedValue({}),
    };

    const seoUrlTemplateRepository = {
        route: '/seo-url-template',
        schema: { entity: 'seo_url_template' },
        // first call (no sales channel) returns the defaults, the next call returns the templates
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
            { id: STOREFRONT_SALES_CHANNEL_ID, typeId: STOREFRONT_TYPE_ID, domains: [] },
            // a headless channel needs a domain flagged as external storefront to support SEO URLs
            { id: HEADLESS_SALES_CHANNEL_ID, typeId: HEADLESS_TYPE_ID, domains: [{ isExternalStorefront: true }] },
            {
                id: HEADLESS_NO_DOMAIN_SALES_CHANNEL_ID,
                typeId: HEADLESS_TYPE_ID,
                domains: [{ isExternalStorefront: false }],
            },
            { id: PRODUCT_COMPARISON_SALES_CHANNEL_ID, typeId: PRODUCT_COMPARISON_TYPE_ID, domains: [] },
            { id: AGENTIC_COMMERCE_SALES_CHANNEL_ID, typeId: AGENTIC_COMMERCE_TYPE_ID, domains: [] },
        ]),
    };

    const wrapper = mount(await wrapTestComponent('sw-seo-url-template-card', { sync: true }), {
        global: {
            stubs: {
                'mt-card': { template: '<div class="mt-card"><slot name="toolbar" /><slot /></div>' },
                'mt-banner': { template: '<div class="mt-banner"><slot /></div>' },
                'sw-sales-channel-switch': true,
                'sw-container': { template: '<div class="sw-container"><slot /></div>' },
                'sw-inherit-wrapper': true,
                'mt-text-field': true,
                'sw-single-select': true,
                'sw-loader': true,
                'mt-icon': true,
                'router-link': true,
            },
            provide: {
                seoUrlTemplateService,
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

    it('should only display the storefront defaults on the "All Sales Channels" view', async () => {
        const { wrapper } = await createWrapper();

        // the headless (store-api) defaults are kept as a blueprint but not shown on the default view
        expect(routeNamesForSalesChannel(wrapper, null)).toEqual([
            'frontend.detail.page',
            'frontend.landing.page',
            'frontend.navigation.page',
        ]);
    });

    it('should create the store-api template fields from the headless defaults for a headless sales channel', async () => {
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

    it('should show the external-storefront-required notice for a headless channel without such a domain', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.vm.onSalesChannelChanged(HEADLESS_NO_DOMAIN_SALES_CHANNEL_ID);
        await flushPromises();

        expect(wrapper.vm.salesChannelIsHeadless).toBe(true);
        expect(wrapper.vm.headlessSalesChannelHasExternalDomain).toBe(false);

        // no template fields, the notice (with a link to the sales channel) is shown instead
        expect(wrapper.findAll('.sw-seo-url-template-card__seo-url')).toHaveLength(0);
        expect(wrapper.text()).toContain('sw-seo-url-template-card.general.textExternalStorefrontRequired');
        expect(wrapper.find('.sw-seo-url-template-card__external-storefront-link').exists()).toBe(true);
    });

    it('should provide the inherited default placeholder for a headless template of a concrete channel', async () => {
        const { wrapper } = await createWrapper();

        // per-channel headless template inherits the store-api default template value
        expect(
            wrapper.vm.getPlaceholder({ routeName: 'store-api.product.detail', salesChannelId: HEADLESS_SALES_CHANNEL_ID }),
        ).toBe('t');

        // a default row itself ("All Sales Channels") has nothing to inherit
        expect(wrapper.vm.getPlaceholder({ routeName: 'store-api.product.detail', salesChannelId: null })).toBeNull();
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

    it('should persist the filled-in headless template with its value', async () => {
        const { wrapper, seoUrlTemplateRepository } = await createWrapper();

        await wrapper.vm.onSalesChannelChanged(HEADLESS_SALES_CHANNEL_ID);
        await flushPromises();

        const productTemplate = wrapper.vm
            .getTemplatesForSalesChannel(HEADLESS_SALES_CHANNEL_ID)
            .find((template) => template.routeName === 'store-api.product.detail');
        productTemplate.template = 'https://foo.bar/product/{{ product.id }}';

        wrapper.vm.onClickSave();
        await flushPromises();

        expect(seoUrlTemplateRepository.sync).toHaveBeenCalledTimes(1);
        const syncedCollection = [...seoUrlTemplateRepository.sync.mock.calls[0][0]];

        // the filled-in product template is part of the sync payload with its value
        const productSync = syncedCollection.find((entry) => entry.routeName === 'store-api.product.detail');
        expect(productSync.template).toBe('https://foo.bar/product/{{ product.id }}');
    });

    it('should sync the headless default template with the storefront one on the "All Sales Channels" view', async () => {
        const { wrapper, seoUrlTemplateRepository } = await createWrapper();

        // edit the storefront product default on the "All Sales Channels" view
        const productDefault = wrapper.vm
            .getTemplatesForSalesChannel(null)
            .find((template) => template.routeName === 'frontend.detail.page');
        productDefault.template = '{{ product.productNumber }}';

        wrapper.vm.onClickSave();
        await flushPromises();

        expect(seoUrlTemplateRepository.sync).toHaveBeenCalledTimes(1);
        const synced = [...seoUrlTemplateRepository.sync.mock.calls[0][0]];

        // both the storefront default and its headless counterpart are persisted with the same value
        const productHeadlessDefault = synced.find((entry) => entry.routeName === 'store-api.product.detail');
        expect(productHeadlessDefault).toBeTruthy();
        expect(productHeadlessDefault.template).toBe('{{ product.productNumber }}');
        expect(synced.find((entry) => entry.routeName === 'frontend.detail.page').template).toBe(
            '{{ product.productNumber }}',
        );
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

    it('should keep the storefront default order and reuse a store-api template already saved for the channel', async () => {
        const { wrapper } = await createWrapper({
            defaultTemplates: ALL_DEFAULTS,
            // the product store-api template was already saved for this channel and is loaded from the DB
            salesChannelTemplates: [
                entityStub({
                    id: 'saved-product',
                    routeName: 'store-api.product.detail',
                    entityName: 'product',
                    salesChannelId: HEADLESS_SALES_CHANNEL_ID,
                    isHeadless: true,
                    template: 'https://foo.bar/{{ product.id }}',
                }),
            ],
        });

        await wrapper.vm.onSalesChannelChanged(HEADLESS_SALES_CHANNEL_ID);
        await flushPromises();

        const templates = [...wrapper.vm.getTemplatesForSalesChannel(HEADLESS_SALES_CHANNEL_ID)];

        // store-api routes follow the headless default order, product not duplicated
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

    it('should store the backend error detail for template errors', async () => {
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

        expect(wrapper.vm.errorMessages[entity.id]).toBe('Twig syntax error');
    });
});
