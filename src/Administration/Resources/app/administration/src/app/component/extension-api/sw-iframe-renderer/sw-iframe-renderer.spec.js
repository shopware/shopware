/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { location } from '@shopware-ag/meteor-admin-sdk';
import useTheme from 'src/app/composables/use-theme';

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
        // Clear extension store
        Object.keys(Shopware.Store.get('extensions').extensionsState).forEach((key) => {
            delete Shopware.Store.get('extensions').extensionsState[key];
        });

        // Clear sdkLocation store
        Object.keys(Shopware.Store.get('sdkLocation').locations).forEach((key) => {
            delete Shopware.Store.get('sdkLocation').locations[key];
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

    it('should not call signIframeSrc for plugins', async () => {
        Shopware.Store.get('extensions').addExtension({
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

        // Plugin iframes must stay keyboard-reachable inside focus traps as well
        expect(wrapper.find('iframe').attributes('tabindex')).toBe('0');
    });

    it('should call signIframeSrc for apps', async () => {
        Shopware.Store.get('extensions').addExtension({
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
            'https://example.com/?location-id=foo&color-scheme=light&shop-id=__SHOP_ID&shop-signature=__SIGNED__',
        );
    });

    it('should append the resolved dark theme as color-scheme to the iFrame src', async () => {
        useTheme().setTheme('dark');

        Shopware.Store.get('extensions').addExtension({
            name: 'foo',
            baseUrl: 'https://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        try {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm.signedIframeSrc).toBe(
                'https://example.com/?location-id=foo&color-scheme=dark&shop-id=__SHOP_ID&shop-signature=__SIGNED__',
            );
        } finally {
            useTheme().setTheme('system');
            localStorage.removeItem('mt-theme');
        }
    });

    it('should render correct iFrame src when parameters are given', async () => {
        Shopware.Store.get('extensions').addExtension({
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
            'http://localhost:8888/index.html?elementId=018d83de67d471d69a03e4742767f1d7&location-id=ex-dailymotion-element&color-scheme=light&shop-id=__SHOP_ID&shop-signature=__SIGNED__',
        );
    });

    it('should render iFrame', async () => {
        Shopware.Store.get('extensions').addExtension({
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

        // Keeps the iframe reachable inside focus traps, e.g. the sidebar overlay
        expect(iFrame.attributes('tabindex')).toBe('0');

        const testComponent = wrapper.find('#my-replacement-component');
        expect(testComponent.exists()).toBe(false);
    });

    it('should render iFrame with replacement component', async () => {
        Shopware.Store.get('extensions').addExtension({
            name: 'foo',
            baseUrl: 'https://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        Shopware.Store.get('sdkLocation').addLocation({
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

        Shopware.Store.get('extensions').addExtension({
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
            'https://my-great-extension.com/app/?location-id=my-great-extension-main-module&color-scheme=light&shop-id=__SHOP_ID&shop-signature=__SIGNED__&search=T-Shirt#/detail/1',
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

        Shopware.Store.get('extensions').addExtension({
            name: 'my-great-extension',
            baseUrl: 'https://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        window.history.replaceState({}, '', 'http://localhost/?location-id=my-great-extension-main-module');

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

        Shopware.Store.get('extensions').addExtension({
            name: 'my-great-extension',
            baseUrl: 'https://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        window.history.replaceState({}, '', 'http://localhost/?location-id=other-location-id');

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
        Shopware.Store.get('extensions').addExtension({
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
            'http://localhost:8888/index.html?elementId=018d83de67d471d69a03e4742767f1d7&location-id=ex-dailymotion-element&color-scheme=light&shop-id=__SHOP_ID&shop-signature=__SIGNED__',
        );

        // Update location ID
        await wrapper.setProps({
            locationId: 'ex-youtube-element',
        });

        await flushPromises();

        const updatedIframe = wrapper.find('iframe');
        const updatedIframeSrc = updatedIframe.attributes('src');

        expect(updatedIframeSrc).toBe(
            'http://localhost:8888/index.html?elementId=018d83de67d471d69a03e4742767f1d7&location-id=ex-youtube-element&color-scheme=light&shop-id=__SHOP_ID&shop-signature=__SIGNED__',
        );
    });

    it('should trigger full page reload when iframe is reloaded after initial load', async () => {
        Shopware.Store.get('extensions').addExtension({
            name: 'foo',
            baseUrl: 'https://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        const wrapper = await createWrapper();
        await flushPromises();

        jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

        // First load (initial): should not reload the page
        const iframe = wrapper.find('iframe');
        expect(iframe.exists()).toBe(true);

        await iframe.trigger('load');
        expect(wrapper.vm._reloadPage).not.toHaveBeenCalled();

        // Second load (iframe reload): should trigger full page reload
        await iframe.trigger('load');
        expect(wrapper.vm._reloadPage).toHaveBeenCalledTimes(1);
    });
});
