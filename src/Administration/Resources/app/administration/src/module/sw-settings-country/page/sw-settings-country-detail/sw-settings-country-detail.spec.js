/**
 * @sw-package fundamentals@discovery
 */
import { mount } from '@vue/test-utils';

const countrySaveMock = jest.fn(() => Promise.resolve());

async function createWrapper(
    privileges = [],
    {
        featureActive = false,
        routeName = 'sw.settings.country.detail.general',
        routeParams = { id: 'the-id' },
        routerPush = jest.fn(),
    } = {},
) {
    return mount(
        await wrapTestComponent('sw-settings-country-detail', {
            sync: true,
        }),
        {
            global: {
                directives: {
                    tooltip: {},
                },

                mocks: {
                    $t: (key) => key,
                    $route: {
                        name: routeName,
                        params: routeParams,
                    },
                    $router: {
                        push: routerPush,
                    },
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
                            save: countrySaveMock,
                            hasChanges: () => false,
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
                    feature: {
                        isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
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
                    'sw-tabs': {
                        name: 'sw-tabs',
                        template: '<div class="sw-tabs"><slot /></div>',
                        props: [
                            'positionIdentifier',
                        ],
                    },
                    'sw-tabs-item': {
                        name: 'sw-tabs-item',
                        template: '<div class="sw-tabs-item"><slot /></div>',
                        props: [
                            'route',
                        ],
                    },
                    'router-link': true,
                    'router-view': true,
                    'sw-skeleton': true,
                    'sw-settings-country-sidebar': true,
                    'sw-error-summary': true,
                    'sw-custom-field-set-renderer': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
                        template: '<div class="mt-tabs"></div>',
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
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
        countrySaveMock.mockClear();
        jest.spyOn(Shopware.Service('userConfigService'), 'search').mockResolvedValue({ data: {} });
        jest.spyOn(Shopware.Service('userConfigService'), 'upsert').mockResolvedValue();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('should render the deprecated tabs when the major feature flag is inactive', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.vm.$nextTick();

        const tabs = wrapper.getComponent({ name: 'sw-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-settings-country-detail-header');
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should keep the fallback tab route contract', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.vm.$nextTick();

        const tabItems = wrapper.findAllComponents({ name: 'sw-tabs-item' });

        expect(tabItems).toHaveLength(3);
        expect(tabItems[0].props('route')).toStrictEqual({ name: 'sw.settings.country.detail.general' });
        expect(tabItems[0].text()).toBe('sw-settings-country.page.generalTab');

        expect(tabItems[1].props('route')).toStrictEqual({ name: 'sw.settings.country.detail.state' });
        expect(tabItems[1].text()).toBe('sw-settings-country.page.stateTab');

        expect(tabItems[2].props('route')).toStrictEqual({ name: 'sw.settings.country.detail.address-handling' });
        expect(tabItems[2].text()).toBe('sw-settings-country.page.addressHandlingTab');
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper(
            [
                'country.editor',
            ],
            {
                featureActive: true,
                routeName: 'sw.settings.country.detail.state',
            },
        );

        await wrapper.vm.$nextTick();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-settings-country-detail-header');
        expect(tabs.props('defaultItem')).toBe('sw.settings.country.detail.state');
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({
                label: 'sw-settings-country.page.generalTab',
                name: 'sw.settings.country.detail.general',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-settings-country.page.stateTab',
                name: 'sw.settings.country.detail.state',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-settings-country.page.addressHandlingTab',
                name: 'sw.settings.country.detail.address-handling',
                onClick: expect.any(Function),
            }),
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it('should navigate when a meteor tab item is clicked', async () => {
        const routerPush = jest.fn();
        const wrapper = await createWrapper(
            [
                'country.editor',
            ],
            {
                featureActive: true,
                routerPush,
            },
        );

        await wrapper.vm.$nextTick();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const stateTab = tabs.props('items').find((item) => {
            return item.name === 'sw.settings.country.detail.state';
        });

        stateTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.settings.country.detail.state' });
    });

    it('should use create routes for meteor tabs when the country is new', async () => {
        const wrapper = await createWrapper(
            [
                'country.creator',
            ],
            {
                featureActive: true,
                routeParams: {},
            },
        );

        await wrapper.setData({
            country: {
                isNew: () => true,
            },
        });

        expect(wrapper.vm.countryTabs.map((item) => item.name)).toEqual([
            'sw.settings.country.create.general',
            'sw.settings.country.create.state',
            'sw.settings.country.create.address-handling',
        ]);
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

    it('loads country display settings from the user config service', async () => {
        Shopware.Service('userConfigService').search.mockResolvedValue({
            data: {
                'setting-country': {
                    'the-id': {
                        showPreview: true,
                    },
                },
            },
        });
        const wrapper = await createWrapper([
            'country.editor',
        ]);

        wrapper.vm.countryId = 'the-id';
        await wrapper.vm.loadUserConfig();

        expect(Shopware.Service('userConfigService').search).toHaveBeenCalledWith(['setting-country']);
        expect(wrapper.vm.userConfigValues).toEqual({
            showPreview: true,
        });
    });

    it('saves country display settings through the admin user config store', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);
        wrapper.vm.countryId = 'the-id';
        wrapper.vm.userConfig = {
            value: {
                'the-id': {
                    showPreview: true,
                },
            },
        };

        await wrapper.vm.onSave();

        expect(countrySaveMock).toHaveBeenCalled();
        expect(Shopware.Service('userConfigService').upsert).toHaveBeenCalledWith({
            'setting-country': {
                'the-id': {
                    showPreview: true,
                },
            },
        });
    });
});
