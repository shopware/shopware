/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils_v3';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-location-renderer', {
        sync: true,
    }), {
        props: {
            element: {
                id: '123456789',
            },
            elementData: {
                name: 'example_cms_element_type',
                appData: {
                    baseUrl: 'http://test.example-app.com',
                },
            },
        },
        global: {
            stubs: {},
            provide: {
                cmsService: {
                    getCmsElementRegistry: () => {
                        return {
                            example_cms_element_type: {
                                component: 'foo-bar',
                                disabledConfigInfoTextKey: 'lorem',
                                defaultConfig: {
                                    text: 'lorem',
                                },
                            },
                        };
                    },
                },
            },
        },
    });
}

jest.useFakeTimers();

describe('module/sw-cms/elements/location-renderer/component', () => {
    beforeAll(() => {
        jest.spyOn(Shopware.ExtensionAPI, 'publishData').mockImplementation(() => {});
    });

    beforeEach(() => {
        jest.clearAllMocks();
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should publish with the correct data id', async () => {
        await createWrapper();

        await flushPromises();

        expect(Shopware.ExtensionAPI.publishData).toHaveBeenCalledTimes(2);
        // First call is just for backwards compatibility
        expect(Shopware.ExtensionAPI.publishData).toHaveBeenNthCalledWith(
            1,
            {
                id: 'example_cms_element_type__config-element',
                path: 'element',
                scope: expect.anything(),
            },
        );

        expect(Shopware.ExtensionAPI.publishData).toHaveBeenNthCalledWith(
            2,
            {
                id: 'example_cms_element_type__config-element__123456789',
                path: 'element',
                scope: expect.anything(),
            },
        );
    });
});
