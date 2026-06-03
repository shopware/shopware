/**
 * @package admin
 */

import Vue from 'vue';
import { mount } from '@vue/test-utils_v3';
import { location } from '@shopware-ag/admin-extension-sdk';

let $routeMock = {
    query: {},
};
let $routerMock = {
    replace: jest.fn(),
};

async function createWrapper({
    propsData = {},
    signIframeSrc = (url) => Promise.resolve({
        uri: `https://${url}.com/?shop-id=__SHOP_ID&shop-signature=__SIGNED__`,
    }),
} = {}) {
    return mount(await wrapTestComponent('sw-iframe-renderer', { sync: true }), {
        props: {
            src: 'https://example.com',
            locationId: 'foo',
            ...propsData,
        },
        global: {
            stubs: {
                'my-replacement-component': {
                    template: '<h1 id="my-replacement-component">Replacement component</h1>',
                },
            },
            provide: {
                extensionSdkService: {
                    signIframeSrc,
                },
            },
            mocks: {
                $route: $routeMock,
                $router: $routerMock,
            },
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
            Vue.delete(Shopware.State.get('extensions'), key);
        });

        // Clear sdkLocation store
        Object.keys(Shopware.State.get('sdkLocation').locations).forEach((key) => {
            Vue.delete(Shopware.State.get('sdkLocation').locations, key);
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

        expect(wrapper.vm.signedIframeSrc).toBe('https://foo.com/?shop-id=__SHOP_ID&shop-signature=__SIGNED__');
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
                ['search', 'T-Shirt'],
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

        const wrapper = await createWrapper({
            propsData: {
                locationId: 'my-great-extension-main-module',
            },
        });
        await flushPromises();

        expect(wrapper.vm.signedIframeSrc).toBe('https://my-great-extension.com/app/?shop-id=__SHOP_ID&shop-signature=__SIGNED__&search=T-Shirt#/detail/1');
    });

    it('should handle location url updates', async () => {
        $routeMock.query = {
            // mock query params inside iFrame
            'locationId_my-great-extension-main-module_searchParams': JSON.stringify([
                ['search', 'T-Shirt'],
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

        window.location = new URL('https://my-great-extension.com/app/?shop-id=__SHOP_ID&shop-signature=__SIGNED__&location-id=my-great-extension-main-module&search=T-Shirt#/detail/1');

        await createWrapper({
            propsData: {
                locationId: 'my-great-extension-main-module',
            },
        });

        await flushPromises();

        await location.updateUrl(new URL(
            'https://my-great-extension.com/app/?search=Shorts#/detail/2',
        ));

        await flushPromises();

        expect($routerMock.replace).toHaveBeenCalledWith({
            query: {
                'locationId_my-great-extension-main-module_searchParams': JSON.stringify([
                    ['search', 'Shorts'],
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
                ['search', 'T-Shirt'],
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

        window.location = new URL('https://my-great-extension.com/app/?shop-id=__SHOP_ID&shop-signature=__SIGNED__&location-id=my-great-extension-other-module&search=T-Shirt#/detail/1');

        await createWrapper({
            propsData: {
                locationId: 'my-great-extension-main-module',
            },
        });

        await flushPromises();

        await location.updateUrl(new URL(
            'https://my-great-extension.com/app/?search=Shorts#/detail/2',
        ));

        await flushPromises();

        expect($routerMock.replace).not.toHaveBeenCalled();
    });

    it('should re-sign the iFrame when the locationId changes for the same app baseUrl', async () => {
        // Two app modules (e.g. an app's "Overview" and "Settings" menu items) share the same
        // baseUrl and only differ by their location-id. Switching between them must re-sign the
        // iFrame src for the new location-id, otherwise the iFrame keeps showing the old location.
        Shopware.State.commit('extensions/addExtension', {
            name: 'my-great-extension',
            baseUrl: 'https://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            active: true,
        });

        const signIframeSrc = jest.fn((name) => Promise.resolve({
            uri: `https://${name}.com/?shop-id=__SHOP_ID&shop-signature=__SIGNED__`,
        }));

        const wrapper = await createWrapper({
            propsData: {
                locationId: 'my-great-extension-overview',
            },
            signIframeSrc,
        });
        await flushPromises();

        // initial sign uses the overview location-id
        expect(signIframeSrc).toHaveBeenCalledWith(
            'my-great-extension',
            expect.stringContaining('location-id=my-great-extension-overview'),
        );

        signIframeSrc.mockClear();

        // navigate to the settings module of the same app (same baseUrl, different location-id)
        await wrapper.setProps({ locationId: 'my-great-extension-settings' });
        await flushPromises();

        expect(signIframeSrc).toHaveBeenCalledWith(
            'my-great-extension',
            expect.stringContaining('location-id=my-great-extension-settings'),
        );
    });
});
