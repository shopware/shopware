import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/structure/sw-page';
import SwExtensionsAppModulePage from 'src/module/sw-extension/page/sw-extension-app-module-page';
import SwExtensionsAppModuleErrorPage from 'src/module/sw-extension/component/sw-extension-app-module-error-page';

import testApps from '../../../../app/service/_mocks/testApps.json';

Shopware.Component.register('sw-extension-app-module-page', SwExtensionsAppModulePage);
Shopware.Component.register('sw-extension-my-apps-error-page', SwExtensionsAppModuleErrorPage);

/**
 * @package services-settings
 */
describe('src/module/sw-extension/page/sw-extension-app-module-page/index.js', () => {
    beforeEach(() => {
        Shopware.State.get('session').currentLocale = 'en-GB';
        Shopware.State.commit('shopwareApps/setApps', testApps);
    });

    async function createWrapper(propsData) {
        const localVue = createLocalVue();
        localVue.filter('asset', value => value);

        // @ts-ignore
        return shallowMount(await Shopware.Component.build('sw-extension-app-module-page'), {
            localVue,
            propsData,
            stubs: {
                'sw-extension-app-module-error-page': await Shopware.Component.build('sw-extension-my-apps-error-page'),
                'sw-page': await Shopware.Component.build('sw-page'),
                'sw-notification-center': true,
                'sw-help-center': true,
                'sw-search-bar': true,
                'sw-app-actions': true,
                'sw-loader': true,
                'sw-button': true,
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
                        return Promise.resolve({ uri: `${source}?timestamp=signed` });
                    },
                },
            },
        });
    }

    it('sets the correct heading and source with a regular module', async () => {
        const wrapper = await createWrapper({
            appName: 'testAppA',
            moduleName: 'standardModule',
        });
        await flushPromises();

        expect(wrapper.get('.smart-bar__header h2').text()).toBe('test App A english - Standard module');
        expect(wrapper.get('iframe#app-content').attributes('src')).toBe('https://shopware.apps/module1?timestamp=signed');
    });

    it('sets the correct heading and source with a main module', async () => {
        const wrapper = await createWrapper({
            appName: 'testAppA',
        });

        expect(wrapper.get('.smart-bar__header h2').text()).toBe('test App A english');
        expect(wrapper.get('iframe#app-content').attributes('src')).toBe('https://shopware.apps/login?timestamp=signed');
    });

    it('shows no iframe and default heading if module is not found', async () => {
        const wrapper = await createWrapper({
            appName: 'notInStore',
            moduleName: 'notAvailable',
        });

        expect(wrapper.get('.smart-bar__header h2').text()).toBe('sw-extension-my-apps.general.mainMenuItemGeneral');
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
        await wrapper.vm.$nextTick();

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
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-extension-app-module-error-page').exists()).toBe(false);
        expect(wrapper.find('sw-loader-stub').exists()).toBe(false);
    });
});
