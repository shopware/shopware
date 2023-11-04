/**
 * @package admin
 */

import Vue from 'vue';
import { shallowMount } from '@vue/test-utils';
import 'src/app/component/extension-api/sw-iframe-renderer';

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-iframe-renderer'), {
        stubs: {
            'my-replacement-component': {
                template: '<h1 id="my-replacement-component">Replacement component</h1>',
            },
        },
        provide: {
            extensionSdkService: {
                signIframeSrc(url) {
                    return Promise.resolve({
                        uri: `${url}__SIGNED__`,
                    });
                },
            },
        },
        propsData: {
            src: 'https://example.com',
            locationId: 'foo',
        },
    });
}

describe('src/app/component/extension-api/sw-iframe-renderer', () => {
    beforeEach(async () => {
        // Clear extension store
        Object.keys(Shopware.State.get('extensions')).forEach((key) => {
            Vue.delete(Shopware.State.get('extensions'), key);
        });

        // Clear sdkLocation store
        Object.keys(Shopware.State.get('sdkLocation').locations).forEach((key) => {
            Vue.delete(Shopware.State.get('sdkLocation').locations, key);
        });
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

        expect(wrapper.vm.signedIframeSrc).toBe('foo__SIGNED__');
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
});
