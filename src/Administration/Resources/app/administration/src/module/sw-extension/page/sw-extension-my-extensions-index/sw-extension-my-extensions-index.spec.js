/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(routeName = 'sw.extension.my-extensions.listing.app') {
    const wrapper = mount(
        await wrapTestComponent('sw-extension-my-extensions-index', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-meteor-page': await wrapTestComponent('sw-meteor-page', { sync: true }),
                    'sw-search-bar': true,
                    'sw-tabs-item': {
                        name: 'sw-tabs-item',
                        template: '<div class="sw-tabs-item"><slot /></div>',
                    },
                    'mt-tabs': {
                        name: 'mt-tabs',
                        props: ['defaultItem', 'items', 'positionIdentifier'],
                        template: '<div />',
                    },
                    'sw-extension-file-upload': {
                        template: '<div class="sw-extension-file-upload"></div>',
                    },
                    'router-view': true,
                    'sw-notification-center': true,
                    'sw-help-center-v2': true,
                    'sw-meteor-navigation': true,
                    'sw-tabs': {
                        name: 'sw-tabs',
                        template: '<div class="sw-tabs"><slot /></div>',
                    },
                    'sw-app-topbar-button': true,
                    'sw-app-topbar-sidebar': true,
                },
                mocks: {
                    $route: {
                        name: routeName,
                        query: {
                            term: '',
                            limit: 5,
                        },
                    },
                    $router: {
                        push: jest.fn(),
                        replace: jest.fn(),
                    },
                },
            },
            attachTo: document.body,
        },
    );

    await flushPromises();

    return wrapper;
}

describe('module/sw-extension/page/sw-extension-my-extensions-index', () => {
    beforeAll(() => {
        if (Shopware.Store.get('context')) {
            Shopware.Store.unregister('context');
        }

        Shopware.Store.register({
            id: 'context',
            state: () => ({
                app: {
                    config: {
                        settings: {
                            disableExtensionManagement: false,
                        },
                    },
                },
                api: {
                    assetPath: 'http://localhost:8000/bundles/administration/',
                    authToken: {
                        token: 'testToken',
                    },
                },
            }),
        });
    });

    afterEach(() => {
        Shopware.Store.get('context').app.config.settings.disableExtensionManagement = false;
        global.activeAclRoles = [];
        global.activeFeatureFlags = [];
    });

    it('upload button should be there when allowed runtime extension management', async () => {
        global.activeAclRoles = ['system.plugin_upload'];
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-extension-file-upload').exists()).toBe(true);
    });

    it('upload button should be not there when disabling runtime extension management', async () => {
        global.activeAclRoles = ['system.plugin_upload'];
        Shopware.Store.get('context').app.config.settings.disableExtensionManagement = true;
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-extension-file-upload').exists()).toBe(false);
    });

    it('upload button should be not there when missing plugin_upload acl', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-extension-file-upload').exists()).toBe(false);
    });

    it('renders legacy sw-tabs items when the major migration is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findAllComponents({ name: 'sw-tabs-item' })).toHaveLength(4);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('renders mt-tabs with extension items when the major migration is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        expect(wrapper.findAllComponents({ name: 'sw-tabs-item' })).toHaveLength(0);
        expect(wrapper.find('.sw-meteor-page__smart-bar-tabs').findComponent({ name: 'mt-tabs' }).exists()).toBe(true);
        expect(wrapper.find('.sw-meteor-page__content').findComponent({ name: 'router-view' }).exists()).toBe(true);
        expect(mtTabs.props('positionIdentifier')).toBe('sw-extension-my-extensions-index');
        expect(mtTabs.props('defaultItem')).toBe('app');
        expect(mtTabs.props('items')).toEqual([
            {
                label: 'sw-extension.my-extensions.tabs.app',
                name: 'app',
                route: { name: 'sw.extension.my-extensions.listing.app', query: { term: undefined, limit: 5, page: 1 } },
                onClick: expect.any(Function),
            },
            {
                label: 'sw-extension.my-extensions.tabs.theme',
                name: 'theme',
                route: { name: 'sw.extension.my-extensions.listing.theme', query: { term: undefined, limit: 5, page: 1 } },
                onClick: expect.any(Function),
            },
            {
                label: 'sw-extension.my-extensions.tabs.recommendation',
                name: 'recommendation',
                route: { name: 'sw.extension.my-extensions.recommendation' },
                onClick: expect.any(Function),
            },
            {
                label: 'sw-extension.my-extensions.tabs.shopwareAccount',
                name: 'account',
                route: { name: 'sw.extension.my-extensions.account' },
                onClick: expect.any(Function),
            },
        ]);
    });

    it('uses the theme tab as default item on direct theme routes', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper('sw.extension.my-extensions.listing.theme');

        expect(wrapper.findComponent({ name: 'mt-tabs' }).props('defaultItem')).toBe('theme');
    });
});
