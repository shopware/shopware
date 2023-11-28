/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils_v3';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-config-location-renderer', {
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

describe('module/sw-cms/elements/location-renderer/config', () => {
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

    it('should add the elementId to iFrame url', async () => {
        const wrapper = await createWrapper();

        const iFrame = wrapper.find('sw-iframe-renderer');

        expect(iFrame.attributes().src).toBe('http://test.example-app.com/?elementId=123456789');
    });
});
