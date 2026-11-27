import { mount } from '@vue/test-utils';
import useTheme from 'src/app/composables/use-theme';

/**
 * @sw-package checkout
 */

import testApps from '../../../../app/service/_mocks/testApps.json';

async function createWrapper(props) {
    // @ts-ignore
    return mount(await wrapTestComponent('sw-extension-app-module-page', { sync: true }), {
        global: {
            stubs: {
                'sw-extension-app-module-error-page': await wrapTestComponent('sw-extension-app-module-error-page', {
                    sync: true,
                }),
                'sw-page': await wrapTestComponent('sw-page', {
                    sync: true,
                }),
                'sw-notification-center': true,
                'sw-help-center': true,
                'sw-search-bar': true,
                'sw-app-actions': true,
                'sw-loader': true,
                'sw-app-topbar-button': true,
                'sw-app-topbar-sidebar': true,
                'sw-help-center-v2': true,
                'sw-context-menu-item': true,
                'sw-context-button': true,
                'router-link': true,
            },
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            title: 'sw-extension-my-apps.general.mainMenuItemGeneral',
                        },
                    },
                },
            },
            provide: {
                extensionSdkService: {
                    signIframeSrc(_, source) {
                        const url = new URL(source);
                        url.searchParams.append('timestamp', 'signed');

                        return Promise.resolve({
                            uri: url.toString(),
                        });
                    },
                },
            },
        },
        props,
    });
}

/**
 * @sw-package checkout
 */
describe('src/module/sw-extension/page/sw-extension-app-module-page/index.js', () => {
    beforeEach(() => {
        Shopware.Store.get('session').currentLocale = 'en-GB';
        Shopware.Store.get('shopwareApps').apps = testApps;
        Shopware.Store.get('shopwareApps').appsLoaded = true;
    });

    it('sets the correct heading and source with a regular module', async () => {
        const wrapper = await createWrapper({
            appName: 'testAppA',
            moduleName: 'standardModule',
        });
        await flushPromises();

        expect(wrapper.get('.smart-bar__header h2').text()).toBe('test App A english - Standard module');
        expect(wrapper.get('iframe#app-content').attributes('src')).toBe(
            'https://shopware.apps/module1?color-scheme=light&timestamp=signed',
        );
    });

    it('sets the correct heading and source with a main module', async () => {
        const wrapper = await createWrapper({
            appName: 'testAppA',
        });

        expect(wrapper.get('.smart-bar__header h2').text()).toBe('test App A english');
        expect(wrapper.get('iframe#app-content').attributes('src')).toBe(
            'https://shopware.apps/login?color-scheme=light&timestamp=signed',
        );
    });

    it('appends the resolved dark theme as color-scheme to the source', async () => {
        useTheme().setTheme('dark');

        try {
            const wrapper = await createWrapper({
                appName: 'testAppA',
                moduleName: 'standardModule',
            });
            await flushPromises();

            expect(wrapper.get('iframe#app-content').attributes('src')).toBe(
                'https://shopware.apps/module1?color-scheme=dark&timestamp=signed',
            );
        } finally {
            useTheme().setTheme('system');
            localStorage.removeItem('mt-theme');
        }
    });

    it('shows no iframe and default heading if module is not found', async () => {
        const wrapper = await createWrapper({
            appName: 'notInStore',
            moduleName: 'notAvailable',
        });

        expect(wrapper.get('.smart-bar__header h2').text()).toBe('sw-extension-my-apps.general.mainMenuItemGeneral');
    });

    it('shows the error page for an unknown app once apps are loaded', async () => {
        const wrapper = await createWrapper({
            appName: 'notInStore',
            moduleName: 'notAvailable',
        });
        await flushPromises();

        expect(wrapper.find('.sw-extension-app-module-error-page').exists()).toBe(true);
        expect(wrapper.find('sw-loader-stub').exists()).toBe(false);
    });

    it('shows a loader instead of the error page while apps are still loading', async () => {
        Shopware.Store.get('shopwareApps').appsLoaded = false;

        const wrapper = await createWrapper({
            appName: 'notInStore',
            moduleName: 'notAvailable',
        });
        await flushPromises();

        expect(wrapper.find('.sw-extension-app-module-error-page').exists()).toBe(false);
        expect(wrapper.find('sw-loader-stub').exists()).toBe(true);
    });

    it('shows error page if module can not load', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper({
            appName: 'testAppA',
            moduleName: 'standardModule',
        });

        wrapper.get('sw-loader-stub');

        // wait for timeout and refresh component
        jest.runAllTimers();
        await flushPromises();

        wrapper.get('.sw-extension-app-module-error-page');
        expect(wrapper.find('sw-loader-stub').exists()).toBe(false);
    });

    it('shows removes loader if iframe is loaded', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper({
            appName: 'testAppA',
            moduleName: 'standardModule',
        });

        wrapper.get('sw-loader-stub');

        const event = new MessageEvent('message', {
            origin: 'https://shopware.apps',
            data: 'sw-app-loaded',
        });

        window.dispatchEvent(event);

        // wait for timeout and refresh component
        jest.runAllTimers();
        await flushPromises();

        expect(wrapper.find('.sw-extension-app-module-error-page').exists()).toBe(false);
        expect(wrapper.find('sw-loader-stub').exists()).toBe(false);
    });

    it('should be able to toggle the page smart bar', async () => {
        const wrapper = await createWrapper({
            appName: 'testAppA',
            moduleName: 'standardModule',
        });
        expect(wrapper.find('.smart-bar__content').exists()).toBeTruthy();

        Shopware.Store.get('extensionSdkModules').addHiddenSmartBar('standardModule');
        await flushPromises();

        expect(wrapper.find('.smart-bar__content').exists()).toBeFalsy();
    });
});
