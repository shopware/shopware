import { shallowMount } from '@vue/test-utils';
import 'src/app/component/extension-api/sw-iframe-renderer';
import flushPromises from 'flush-promises';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-iframe-renderer'), {
        stubs: {},
        provide: {
            extensionSdkService: {
                signIframeSrc(url) {
                    return Promise.resolve({
                        uri: `${url}__SIGNED__`,
                    });
                }
            },
        },
        propsData: {
            src: 'https://example.com',
            locationId: 'foo',
        }
    });
}

describe('src/app/component/extension-api/sw-iframe-renderer', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
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

        const wrapper = createWrapper();
        await flushPromises();

        expect(wrapper.vm.signedIframeSrc).toBe(null);
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

        const wrapper = createWrapper();
        await flushPromises();

        expect(wrapper.vm.signedIframeSrc).toBe('foo__SIGNED__');
    });
});
