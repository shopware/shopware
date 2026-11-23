/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/service/sales-channel-favorites.service';

async function createWrapper(typeId: string) {
    return mount(await wrapTestComponent('sw-sales-channel-create-base', { sync: true }), {
        global: {
            stubs: {
                'mt-card': { template: '<div class="mt-card"><slot></slot></div>' },
                'sw-container': { template: '<div class="sw-container"><slot></slot></div>' },
                'sw-text-field': true,
                'mt-number-field': true,
                'sw-entity-single-select': true,
                'sw-single-select': true,
                'sw-sales-channel-defaults-select': true,
                'router-link': true,
                'sw-radio-field': true,
                'sw-multi-tag-ip-select': true,
                'sw-select-number-field': true,
                'sw-select-field': true,
                'sw-help-text': true,
                'sw-sales-channel-detail-hreflang': true,
                'sw-sales-channel-detail-domains': true,
                'sw-agentic-commerce-tracking-config': true,
                'sw-category-tree-field': true,
                'mt-select': true,
                'sw-custom-field-set-renderer': true,
                'sw-form-field-renderer': true,
                'mt-banner': true,
                'sw-sales-channel-measurement': true,
                'sw-time-ago': true,
            },
            provide: {
                salesChannelService: { generateKey: () => Promise.resolve({ accessKey: 'access-key' }) },
                productExportService: {},
                knownIpsService: { getKnownIps: () => Promise.resolve() },
                systemConfigApiService: {
                    getConfig: () => Promise.resolve([]),
                    getValues: () => Promise.resolve({}),
                    saveValues: () => Promise.resolve(),
                },
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([]),
                        get: () => Promise.resolve(null),
                        delete: () => Promise.resolve(),
                    }),
                },
            },
            mocks: {
                $router: { resolve: () => ({ href: '/' }) },
            },
        },
        props: {
            salesChannel: { typeId, active: false },
            productExport: {},
            customFieldSets: [],
        },
    });
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-create-base', () => {
    beforeAll(() => {
        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- a test double, not a feature
        Shopware.Service().register('timezoneService', () => ({
            getTimezoneOptions: () => [{ label: 'UTC', value: 'UTC' }],
        }));
    });

    beforeEach(() => {
        // @ts-expect-error - activeAclRoles is an untyped test global
        global.activeAclRoles = ['sales_channel.editor'];
    });

    async function activeSwitch(typeId: string) {
        const wrapper = await createWrapper(typeId);
        await flushPromises();

        return wrapper.get('.mt-switch input[aria-label="sw-sales-channel.detail.labelInputActive"]');
    }

    // A sales channel with a storefront needs a theme to be active, and a theme can only be assigned once it is saved.
    it('does not let an editor activate a storefront sales channel that is not saved yet', async () => {
        const field = await activeSwitch(Shopware.Defaults.storefrontSalesChannelTypeId);

        expect(field.attributes('disabled')).toBeDefined();
    });

    it('lets an editor activate a headless sales channel while creating it', async () => {
        const field = await activeSwitch(Shopware.Defaults.apiSalesChannelTypeId);

        expect(field.attributes('disabled')).toBeUndefined();
    });

    it('does not let a viewer activate a headless sales channel', async () => {
        // @ts-expect-error - activeAclRoles is an untyped test global
        global.activeAclRoles = [];

        const field = await activeSwitch(Shopware.Defaults.apiSalesChannelTypeId);

        expect(field.attributes('disabled')).toBeDefined();
    });
});
