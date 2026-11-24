/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import SystemConfigApiService from 'src/core/service/api/system-config.api.service';
import { deprecations } from 'test/_helper_/deprecations';

const mockGetSystemConfig = jest.fn(() => Promise.resolve([]));

/**
 * The real system config API service, so the deprecated `getConfig()` still runs its guard.
 */
function createSystemConfigApiService() {
    const httpClient = {
        get: async (url, { params }) => ({ data: await mockGetSystemConfig(params.domain) }),
    };

    return Object.assign(new SystemConfigApiService(httpClient, { getToken: () => 'token' }), {
        getValues: () => Promise.resolve({}),
        batchSave: () => Promise.resolve(),
    });
}

async function createWrapper({ salesChannelResponse = {} } = {}) {
    return mount(await wrapTestComponent('sw-sales-channel-detail', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: '<div class="sw-page"><slot name="smart-bar-actions" /><slot name="content" /></div>',
                },
                'sw-card-view': {
                    template: '<div class="sw-card-view"><slot /></div>',
                },
                'sw-tabs': {
                    template: '<div class="sw-tabs"><slot /></div>',
                },
                'sw-tabs-item': {
                    template: '<div class="sw-tabs-item"><slot /></div>',
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    template: '<div class="mt-tabs"></div>',
                    props: ['items', 'defaultItem', 'positionIdentifier'],
                },
                'mt-banner': {
                    template: '<div class="mt-banner"><slot /></div>',
                },
                'sw-button-process': true,
                'sw-language-switch': true,
                'sw-language-info': true,
                'router-view': true,
                'sw-skeleton': true,
                'mt-button': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => ({}),
                        get: () =>
                            Promise.resolve({
                                id: '1a2b3c4d',
                                typeId: Shopware.Defaults.storefrontSalesChannelTypeId,
                                productExports: { first: () => ({}) },
                                ...salesChannelResponse,
                            }),
                        search: () => Promise.resolve([]),
                        save: () => Promise.resolve(),
                    }),
                },
                exportTemplateService: {
                    getProductExportTemplateRegistry: () => ({}),
                },
                systemConfigApiService: createSystemConfigApiService(),
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
            },
            mocks: {
                $route: { params: { id: '1a2b3c4d' }, name: '' },
                $router: { push: jest.fn() },
            },
        },
    });
}

/**
 * Tab labels of whichever tab implementation rendered.
 */
function tabLabels(wrapper) {
    const meteorTabs = wrapper.findComponent({ name: 'mt-tabs' });

    if (meteorTabs.exists()) {
        return meteorTabs.props('items').map((item) => item.label);
    }

    return wrapper.findAll('.sw-tabs-item').map((tab) => tab.text());
}

describe('src/module/sw-sales-channel/page/sw-sales-channel-detail/agentic-commerce-deprecations', () => {
    // @deprecated tag:v6.8.0 - Test will be removed together with the Agentic Commerce members.
    it.deprecated('v6.8.0.0').each([
        ['isAgenticCommerce', (vm) => vm.isAgenticCommerce],
        ['hasSwagAgenticCommercePlugin', (vm) => vm.hasSwagAgenticCommercePlugin],
        ['showAgenticCommerceDeprecationBanner', (vm) => vm.showAgenticCommerceDeprecationBanner],
        ['defaultAgenticCommerceExportConfig', (vm) => vm.defaultAgenticCommerceExportConfig],
        [
            'onClickInstallAgenticCommercePlugin()',
            (vm) => {
                vm.$router = { hasRoute: () => false, push: jest.fn() };
                vm.onClickInstallAgenticCommercePlugin();
            },
        ],
        ['validateAgenticCommerceExportConfig()', (vm) => vm.validateAgenticCommerceExportConfig()],
        ['loadAgenticCommerceExportConfig()', (vm) => vm.loadAgenticCommerceExportConfig()],
        ['saveAgenticCommerceExportConfig()', (vm) => vm.saveAgenticCommerceExportConfig()],
    ])('should report the use of the deprecated member %s', async (member, use) => {
        const wrapper = await createWrapper();
        await flushPromises();

        // eslint-disable-next-line sw-test-rules/no-vm-access -- extensions reach the deprecated member on the instance
        await use(wrapper.vm);

        expect(deprecations).toHaveTriggered(`sw-sales-channel-detail.${member} is deprecated.`);
    });

    describe('core use of the deprecated Agentic Commerce members', () => {
        afterEach(() => {
            Shopware.Component.getOverrideRegistry().delete('sw-sales-channel-detail');
        });

        // @deprecated tag:v6.8.0 - Test will be removed together with the Agentic Commerce members.
        it.deprecated('v6.8.0.0')('applies overrides of the deprecated members without reporting them', async () => {
            const loadOverride = jest.fn(() => Promise.resolve());
            Shopware.Component.override('sw-sales-channel-detail', {
                computed: {
                    isAgenticCommerce() {
                        return true;
                    },
                },
                methods: {
                    loadAgenticCommerceExportConfig: loadOverride,
                },
            });

            const wrapper = await createWrapper();
            await flushPromises();

            expect(loadOverride).toHaveBeenCalled();
            expect(tabLabels(wrapper)).toContain('sw-sales-channel.detail.agenticCommerce.tabIntegration');
            expect(deprecations).not.toHaveTriggered('sw-sales-channel-detail.isAgenticCommerce is deprecated');
            expect(deprecations).not.toHaveTriggered(
                'sw-sales-channel-detail.loadAgenticCommerceExportConfig() is deprecated',
            );
        });

        // @deprecated tag:v6.8.0 - Test will be removed together with the Agentic Commerce members.
        it.deprecated('v6.8.0.0')('keeps reporting deprecations in the body of the export config load', async () => {
            await createWrapper({
                salesChannelResponse: {
                    typeId: Shopware.Defaults.agenticCommerceTypeId,
                },
            });
            await flushPromises();

            expect(deprecations).toHaveTriggered('systemConfigApiService.getConfig() is deprecated');
        });

        it.activeFeatureFlags(['v6.8.0.0'])(
            'treats an agentic commerce channel as if the deprecated members were removed',
            async () => {
                mockGetSystemConfig.mockClear();

                const wrapper = await createWrapper({
                    salesChannelResponse: {
                        typeId: Shopware.Defaults.agenticCommerceTypeId,
                    },
                });
                await flushPromises();

                expect(mockGetSystemConfig).not.toHaveBeenCalled();
                expect(tabLabels(wrapper)).not.toContain('sw-sales-channel.detail.agenticCommerce.tabIntegration');
                expect(wrapper.find('.mt-banner').exists()).toBe(false);
            },
        );
    });
});
