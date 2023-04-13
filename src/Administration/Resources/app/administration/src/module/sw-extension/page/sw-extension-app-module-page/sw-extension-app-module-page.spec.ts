import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/structure/sw-page';
import SwExtensionsAppModulePage from 'src/module/sw-extension/page/sw-extension-app-module-page';
import SwExtensionsAppModuleErrorPage from 'src/module/sw-extension/component/sw-extension-app-module-error-page';

import testApps from '../../../../app/service/_mocks/testApps.json';

Shopware.Component.register('sw-extension-app-module-page', SwExtensionsAppModulePage);
Shopware.Component.register('sw-extension-my-apps-error-page', SwExtensionsAppModuleErrorPage);

/**
 * @package merchant-services
 */
describe('src/module/sw-extension/page/sw-extension-app-module-page', () => {
    let wrapper = null;

    beforeAll(() => {
        Shopware.State.get('session').currentLocale = 'en-GB';
        Shopware.State.commit('shopwareApps/setApps', testApps);
    });

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    async function createWrapper(propsData) {
        const localVue = createLocalVue();
        localVue.filter('asset', (value) => value);

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
                'sw-button': true
            },
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            title: 'sw-extension-my-apps.general.mainMenuItemGeneral'
                        }
                    }
                }
            }
        });
    }

    describe.each([
        {
            testName: 'regular module',
            propsData: {
                appName: 'testAppA',
                moduleName: 'standardModule'
            },
            expectedHeading: 'test App A english - Standard module',
            expectedSource: 'https://shopware.apps/module1'
        }, {
            testName: 'main module',
            propsData: {
                appName: 'testAppA'
            },
            expectedHeading: 'test App A english',
            expectedSource: 'https://shopware.apps/login'
        }
    ])('It sets the correct heading and source', ({ testName, propsData, expectedHeading, expectedSource }) => {
        it(testName, async () => {
            wrapper = await createWrapper(propsData);

            expect(wrapper.get('.smart-bar__header h2').text()).toBe(expectedHeading);
            expect(wrapper.get('iframe#app-content').attributes('src')).toBe(expectedSource);
        });
    });

    it('shows no iframe and default heading if module is not found', async () => {
        wrapper = await createWrapper({
            appName: 'notInStore',
            moduleName: 'notAvailable'
        });

        expect(wrapper.get('.smart-bar__header h2').text()).toBe('sw-extension-my-apps.general.mainMenuItemGeneral');
    });

    it('shows error page if module can not load', async () => {
        jest.useFakeTimers();

        wrapper = await createWrapper({
            appName: 'testAppA',
            moduleName: 'standardModule'
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

        wrapper = await createWrapper({
            appName: 'testAppA',
            moduleName: 'standardModule'
        });

        wrapper.get('sw-loader-stub');

        const event = new MessageEvent('message', {
            origin: 'https://shopware.apps',
            data: 'sw-app-loaded'
        });

        window.dispatchEvent(event);

        // wait for timeout and refresh component
        jest.runAllTimers();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-extension-app-module-error-page').exists()).toBe(false);
        expect(wrapper.find('sw-loader-stub').exists()).toBe(false);
    });
});
