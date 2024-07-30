/**
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    const wrapper = mount(await wrapTestComponent('sw-extension-my-extensions-index', { sync: true }), {
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
                'sw-icon': true,
                'sw-tabs': true,
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
    });

    await flushPromises();

    return wrapper;
}

describe('module/sw-extension/page/sw-extension-my-extensions-index', () => {
    beforeAll(() => {
        if (Shopware.State.get('context')) {
            Shopware.State.unregisterModule('context');
        }

        Shopware.State.registerModule('context', {
            namespaced: true,
            state: {
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
            },
        });
    });

    it('upload button should be there when allowed runtime extension management', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-extension-file-upload').exists()).toBe(true);
    });

    it('upload button should be not there when allowed runtime extension management', async () => {
        Shopware.State.get('context').app.config.settings.disableExtensionManagement = true;
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-extension-file-upload').exists()).toBe(false);
    });
});
