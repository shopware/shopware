/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/service/sales-channel-favorites.service';
import { deprecations } from 'test/_helper_/deprecations';

async function createWrapper(salesChannel, productExport) {
    return mount(await wrapTestComponent('sw-sales-channel-create-base', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: '<div class="mt-card"><slot></slot></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
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
                salesChannelService: {
                    generateKey: () => Promise.resolve({ accessKey: 'sales-channel-key' }),
                },
                productExportService: {
                    generateKey: () => Promise.resolve({ accessKey: 'product-export-key' }),
                },
                knownIpsService: {
                    getKnownIps: () => Promise.resolve(),
                },
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([]),
                        searchIds: () => Promise.resolve({ total: 0, data: [] }),
                        get: () => Promise.resolve(),
                    }),
                },
            },
        },
        props: {
            salesChannel,
            productExport,
            customFieldSets: [],
        },
    });
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-create-base', () => {
    beforeAll(() => {
        Shopware.Service().register('timezoneService', () => ({
            getTimezoneOptions: () => [],
        }));
    });

    // @deprecated tag:v6.8.0 - Test will be removed together with the Agentic Commerce members.
    it.deprecated('v6.8.0.0')('should prefill the feed file name of a new agentic commerce sales channel', async () => {
        const productExport = {};
        await createWrapper(
            {
                typeId: Shopware.Defaults.agenticCommerceTypeId,
                configuration: {},
            },
            productExport,
        );
        await flushPromises();

        expect(productExport.fileName).toMatch(/^agentic-commerce-\w+\.jsonl$/);
        expect(productExport.accessKey).toBe('product-export-key');
    });

    // @deprecated tag:v6.8.0 - Test will be removed together with the Agentic Commerce members.
    it.deprecated('v6.8.0.0').each([
        ['prefillAgenticCommerceDefaults()', (vm) => vm.prefillAgenticCommerceDefaults()],
    ])('should report the use of the deprecated member %s', async (member, use) => {
        const wrapper = await createWrapper({ typeId: Shopware.Defaults.agenticCommerceTypeId, configuration: {} }, {});
        await flushPromises();

        // eslint-disable-next-line sw-test-rules/no-vm-access -- extensions reach the deprecated member on the instance
        await use(wrapper.vm);

        expect(deprecations).toHaveTriggered(`sw-sales-channel-create-base.${member} is deprecated.`);
    });

    describe('core use of the deprecated Agentic Commerce members', () => {
        afterEach(() => {
            Shopware.Component.getOverrideRegistry().delete('sw-sales-channel-create-base');
        });

        // @deprecated tag:v6.8.0 - Test will be removed together with the Agentic Commerce members.
        it.deprecated('v6.8.0.0')(
            'applies an override of prefillAgenticCommerceDefaults() without reporting it',
            async () => {
                const prefillOverride = jest.fn();
                Shopware.Component.override('sw-sales-channel-create-base', {
                    methods: {
                        prefillAgenticCommerceDefaults: prefillOverride,
                    },
                });

                await createWrapper({ typeId: Shopware.Defaults.agenticCommerceTypeId, configuration: {} }, {});
                await flushPromises();

                expect(prefillOverride).toHaveBeenCalled();
                expect(deprecations).not.toHaveTriggered(
                    'sw-sales-channel-create-base.prefillAgenticCommerceDefaults() is deprecated',
                );
            },
        );

        it.activeFeatureFlags(['v6.8.0.0'])(
            'treats an agentic commerce channel as if the deprecated members were removed',
            async () => {
                const productExport = {};
                await createWrapper({ typeId: Shopware.Defaults.agenticCommerceTypeId, configuration: {} }, productExport);
                await flushPromises();

                expect(productExport.fileName).toBeUndefined();
            },
        );
    });
});
