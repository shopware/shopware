/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    const wrapper = mount(
        await wrapTestComponent('sw-extension-my-extensions-index', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-meteor-page': await wrapTestComponent('sw-meteor-page', { sync: true }),
                    'sw-search-bar': true,
                    'sw-tabs-item': true,
                    'sw-extension-file-upload': {
                        template: '<div class="sw-extension-file-upload"></div>',
                    },
                    'router-view': true,
                    'sw-notification-center': true,
                    'sw-help-center-v2': true,
                    'sw-meteor-navigation': true,
                    'sw-tabs': true,
                    'sw-app-topbar-button': true,
                    'sw-app-topbar-sidebar': true,
                },
                mocks: {
                    $route: {
                        query: {
                            term: '',
                            limit: 5,
                        },
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
});
