/**
 * @sw-package fundamentals@discovery
 */
import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        name: 'index',
        path: '/',
        component: {},
    },
    {
        name: 'sw.settings.country.detail',
        path: '/sw/settings/country/detail/:id',
        children: [
            {
                name: 'sw.settings.country.detail.general',
                path: 'general',
                component: {},
            },
            {
                name: 'sw.settings.country.detail.state',
                path: 'state',
                component: {},
            },
            {
                name: 'sw.settings.country.detail.address-handling',
                path: 'address-handling',
                component: {},
            },
        ],
    },
];

async function createWrapper(privileges = []) {
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const router = createRouter({
        history: createWebHistory(),
        routes: routes,
    });
    await router.push({ name: 'sw.settings.country.detail.general', params: { id: 'the-id' } });
    await router.isReady();

    return mount(
        await wrapTestComponent('sw-settings-country-detail', {
            sync: true,
        }),
        {
            global: {
                plugins: [
                    router,
                ],

                directives: {
                    tooltip: {},
                },

                mocks: {
                    $t: (key) => key,
                    $device: {
                        removeResizeListener: () => {},
                        getSystemKey: () => {},
                        onResize: () => {},
                    },
                },

                provide: {
                    repositoryFactory: {
                        create: (entity) => ({
                            get: () => {
                                if (entity === 'country') {
                                    return Promise.resolve({
                                        isNew: () => false,
                                        active: true,
                                        apiAlias: null,
                                        createdAt: '2020-08-12T02:49:39.974+00:00',
                                        customFields: null,
                                        customerAddresses: [],
                                        displayStateInRegistration: false,
                                        forceStateInRegistration: false,
                                        id: '44de136acf314e7184401d36406c1e90',
                                        iso: 'AL',
                                        iso3: 'ALB',
                                        name: 'Albania',
                                        orderAddresses: [],
                                        position: 10,
                                        salesChannelDefaultAssignments: [],
                                        salesChannels: [],
                                        shippingAvailable: true,
                                        states: [],
                                        taxFree: false,
                                        taxRules: [],
                                        translated: {},
                                        translations: [],
                                        updatedAt: '2020-08-16T06:57:40.559+00:00',
                                        vatIdRequired: false,
                                    });
                                }

                                return Promise.resolve({
                                    systemCurrency: {
                                        symbol: '€',
                                    },
                                });
                            },
                            search: () => {
                                return Promise.resolve({
                                    first: () => ({}),
                                    length: 0,
                                });
                            },
                            create: () => {
                                return {};
                            },
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                },

                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                    },
                    'sw-card-view': await wrapTestComponent('sw-card-view'),
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-language-switch': true,
                    'sw-language-info': true,
                    'sw-button-process': true,
                    'sw-field': true,
                    'sw-simple-search-field': true,
                    'sw-context-menu-item': true,
                    'mt-number-field': true,
                    'sw-one-to-many-grid': true,
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'router-link': true,
                    'router-view': true,
                    'sw-skeleton': true,
                    'sw-settings-country-sidebar': true,
                    'sw-error-summary': true,
                    'sw-custom-field-set-renderer': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
                        props: {
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                default: null,
                            },
                            defaultItem: {
                                type: String,
                                default: '',
                            },
                            routeTabs: {
                                type: Boolean,
                                default: false,
                            },
                        },
                        template: '<div class="mt-tabs-stub"></div>',
                    },
                    'sw-extension-component-section': true,
                },
            },
        },
    );
}

describe('module/sw-settings-country/page/sw-settings-country-detail', () => {
    beforeAll(() => {
        Shopware.Store.get('session').setCurrentUser({});
    });

    beforeEach(() => {
        global.activeFeatureFlags = [];
    });

    it('should render route-backed mt-tabs when the major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper([
            'country.editor',
        ]);
        await flushPromises();

        const routerPush = jest.spyOn(wrapper.vm.$router, 'push').mockResolvedValue();
        const tabs = wrapper.getComponent('.mt-tabs-stub');

        expect(tabs.props('positionIdentifier')).toBe('sw-settings-country-detail-header');
        expect(tabs.props('defaultItem')).toBe('sw.settings.country.detail.general');
        expect(tabs.props('routeTabs')).toBe(true);

        const items = tabs.props('items');
        expect(items).toEqual([
            expect.objectContaining({
                label: 'sw-settings-country.page.generalTab',
                name: 'sw.settings.country.detail.general',
            }),
            expect.objectContaining({
                label: 'sw-settings-country.page.stateTab',
                name: 'sw.settings.country.detail.state',
            }),
            expect.objectContaining({
                label: 'sw-settings-country.page.addressHandlingTab',
                name: 'sw.settings.country.detail.address-handling',
            }),
        ]);

        items[1].onClick();
        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.settings.country.detail.state',
            params: { id: 'the-id' },
        });
    });

    it('should be render tab', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.vm.$nextTick();
        const generalTab = wrapper.find('.sw-settings-country__setting-tab');
        const stateTab = wrapper.find('.sw-settings-country__state-tab');

        expect(generalTab.exists()).toBeTruthy();
        expect(stateTab.exists()).toBeTruthy();
    });

    it('should be able to save the country', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-country-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to save the country', async () => {
        const wrapper = await createWrapper([]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-country-detail__save-action');

        expect(saveButton.attributes().disabled).toBeTruthy();
    });
});
