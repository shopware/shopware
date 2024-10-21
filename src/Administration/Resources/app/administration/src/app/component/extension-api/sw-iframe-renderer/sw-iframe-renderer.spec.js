/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import { location } from '@shopware-ag/meteor-admin-sdk';

let $routeMock = {
    query: {},
};
let $routerMock = {
    replace: jest.fn(),
};

async function createWrapper({ props = {} } = {}) {
    return mount(await wrapTestComponent('sw-iframe-renderer', { sync: true }), {
        props: {
            src: 'https://example.com',
            locationId: 'foo',
            ...props,
        },
        global: {
            stubs: {
                'my-replacement-component': {
                    template: '<h1 id="my-replacement-component">Replacement component</h1>',
                },
            },
            provide: {
                extensionSdkService: {
                    signIframeSrc(extensionName, iframeSrc) {
                        const url = new URL(iframeSrc);

                        // Add search params to the iframe src
                        const searchParams = new URLSearchParams(url.search);
                        searchParams.set('shop-id', '__SHOP_ID');
                        searchParams.set('shop-signature', '__SIGNED__');

                        url.search = searchParams.toString();

                        return Promise.resolve({
                            uri: url.href,
                        });
                    },
                },
            },
            mocks: {
                $route: $routeMock,
                $router: $routerMock,
            },
            attachTo: window.document,
        },
    });
}

describe('src/app/component/extension-api/sw-iframe-renderer', () => {
    beforeEach(async () => {
        // Reset window location search
        delete window.location;
        window.location = new URL('https://www.example.com');

        // Clear extension store
        Object.keys(Shopware.State.get('extensions')).forEach((key) => {
            delete Shopware.State.get('extensions')[key];
        });

        // Clear sdkLocation store
        Object.keys(Shopware.State.get('sdkLocation').locations).forEach((key) => {
            delete Shopware.State.get('sdkLocation').locations[key];
        });

        // Reset route mock
        $routeMock = {
            query: {},
        };

        // Reset router mock
        $routerMock = {
            replace: jest.fn(),
        };
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should not call signIframeSrc for plugins', async () => {
        Shopware.State.commit('extensions/addExtension', {
            name: 'foo',
            baseUrl: 'https://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'plugin',
            active: true,
        });

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.signedIframeSrc).toBeNull();
    });

    it('should call signIframeSrc for apps', async () => {
        Shopware.State.commit('extensions/addExtension', {
            name: 'foo',
            baseUrl: 'https://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.signedIframeSrc).toBe(
            'https://example.com/?location-id=foo&shop-id=__SHOP_ID&shop-signature=__SIGNED__',
        );
    });

    it('should render correct iFrame src when parameters are given', async () => {
        Shopware.State.commit('extensions/addExtension', {
            name: 'MeteorAdminSDKExampleApp',
            baseUrl: 'http://localhost:8888/index.html',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        const wrapper = await createWrapper({
            props: {
                src: 'http://localhost:8888/index.html?elementId=018d83de67d471d69a03e4742767f1d7',
                locationId: 'ex-dailymotion-element',
            },
        });

        await flushPromises();

        const iframe = wrapper.find('iframe');
        const iframeSrc = iframe.attributes('src');

        expect(iframeSrc).toBe(
            'http://localhost:8888/index.html?elementId=018d83de67d471d69a03e4742767f1d7&location-id=ex-dailymotion-element&shop-id=__SHOP_ID&shop-signature=__SIGNED__',
        );
    });

    it('should render iFrame', async () => {
        Shopware.State.commit('extensions/addExtension', {
            name: 'foo',
            baseUrl: 'https://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const iFrame = wrapper.find('iframe');
        expect(iFrame.exists()).toBe(true);

        const testComponent = wrapper.find('#my-replacement-component');
        expect(testComponent.exists()).toBe(false);
    });

    it('should render iFrame with replacement component', async () => {
        Shopware.State.commit('extensions/addExtension', {
            name: 'foo',
            baseUrl: 'https://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        Shopware.State.commit('sdkLocation/addLocation', {
            locationId: 'foo',
            componentName: 'my-replacement-component',
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const iFrame = wrapper.find('iframe');
        expect(iFrame.exists()).toBe(false);

        const testComponent = wrapper.find('#my-replacement-component');
        expect(testComponent.exists()).toBe(true);
    });

    it('should load the correct iFrame route from the query route information', async () => {
        $routeMock.query = {
            // mock query params inside iFrame
            'locationId_my-great-extension-main-module_searchParams': JSON.stringify([
                [
                    'search',
                    'T-Shirt',
                ],
            ]),
            // mock hash route inside iFrame
            'locationId_my-great-extension-main-module_hash': '#/detail/1',
            // mock pathname route inside iFrame
            'locationId_my-great-extension-main-module_pathname': '/app/',
        };

        Shopware.State.commit('extensions/addExtension', {
            name: 'my-great-extension',
            baseUrl: 'https://my-great-extension.com',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        const wrapper = await createWrapper({
            props: {
                locationId: 'my-great-extension-main-module',
                src: 'https://my-great-extension.com/',
            },
        });
        await flushPromises();

        expect(wrapper.vm.signedIframeSrc).toBe(
            'https://my-great-extension.com/app/?location-id=my-great-extension-main-module&shop-id=__SHOP_ID&shop-signature=__SIGNED__&search=T-Shirt#/detail/1',
        );
    });

    it('should handle location url updates', async () => {
        $routeMock.query = {
            // mock query params inside iFrame
            'locationId_my-great-extension-main-module_searchParams': JSON.stringify([
                [
                    'search',
                    'T-Shirt',
                ],
            ]),
            // mock hash route inside iFrame
            'locationId_my-great-extension-main-module_hash': '#/detail/1',
            // mock pathname route inside iFrame
            'locationId_my-great-extension-main-module_pathname': '/app/',
        };

        Shopware.State.commit('extensions/addExtension', {
            name: 'my-great-extension',
            baseUrl: 'https://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        window.location = new URL(
            'https://my-great-extension.com/app/?shop-id=__SHOP_ID&shop-signature=__SIGNED__&location-id=my-great-extension-main-module&search=T-Shirt#/detail/1',
        );

        await createWrapper({
            props: {
                locationId: 'my-great-extension-main-module',
            },
        });

        await flushPromises();

        await location.updateUrl(new URL('https://my-great-extension.com/app/?search=Shorts#/detail/2'));

        await flushPromises();

        expect($routerMock.replace).toHaveBeenCalledWith({
            query: {
                'locationId_my-great-extension-main-module_searchParams': JSON.stringify([
                    [
                        'search',
                        'Shorts',
                    ],
                ]),
                'locationId_my-great-extension-main-module_hash': '#/detail/2',
                'locationId_my-great-extension-main-module_pathname': '/app/',
            },
        });
    });

    it('should handle location url updates for different location ids', async () => {
        $routeMock.query = {
            // mock query params inside iFrame
            'locationId_my-great-extension-main-module_searchParams': JSON.stringify([
                [
                    'search',
                    'T-Shirt',
                ],
            ]),
            // mock hash route inside iFrame
            'locationId_my-great-extension-main-module_hash': '#/detail/1',
            // mock pathname route inside iFrame
            'locationId_my-great-extension-main-module_pathname': '/app/',
        };

        Shopware.State.commit('extensions/addExtension', {
            name: 'my-great-extension',
            baseUrl: 'https://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        window.location = new URL(
            'https://my-great-extension.com/app/?shop-id=__SHOP_ID&shop-signature=__SIGNED__&location-id=my-great-extension-other-module&search=T-Shirt#/detail/1',
        );

        await createWrapper({
            props: {
                locationId: 'my-great-extension-main-module',
            },
        });

        await flushPromises();

        await location.updateUrl(new URL('https://my-great-extension.com/app/?search=Shorts#/detail/2'));

        await flushPromises();

        expect($routerMock.replace).not.toHaveBeenCalled();
    });

    it('should add full screen class to iframe', async () => {
        const wrapper = await createWrapper({
            props: {
                fullScreen: true,
            },
        });
        await flushPromises();

        const iframeRenderer = wrapper.find('.sw-iframe-renderer.sw-iframe-renderer--full-screen');
        expect(iframeRenderer.element instanceof HTMLElement).toBe(true);
    });

    it('should update the iFrame src when location ID changes', async () => {
        Shopware.State.commit('extensions/addExtension', {
            name: 'MeteorAdminSDKExampleApp',
            baseUrl: 'http://localhost:8888/index.html',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        const wrapper = await createWrapper({
            props: {
                src: 'http://localhost:8888/index.html?elementId=018d83de67d471d69a03e4742767f1d7',
                locationId: 'ex-dailymotion-element',
            },
        });

        await flushPromises();

        const iframe = wrapper.find('iframe');
        const iframeSrc = iframe.attributes('src');

        expect(iframeSrc).toBe(
            'http://localhost:8888/index.html?elementId=018d83de67d471d69a03e4742767f1d7&location-id=ex-dailymotion-element&shop-id=__SHOP_ID&shop-signature=__SIGNED__',
        );

        // Update location ID
        await wrapper.setProps({
            locationId: 'ex-youtube-element',
        });

        await flushPromises();

        const updatedIframe = wrapper.find('iframe');
        const updatedIframeSrc = updatedIframe.attributes('src');

        expect(updatedIframeSrc).toBe(
            'http://localhost:8888/index.html?elementId=018d83de67d471d69a03e4742767f1d7&location-id=ex-youtube-element&shop-id=__SHOP_ID&shop-signature=__SIGNED__',
        );
    });
});
