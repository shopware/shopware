/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-location-renderer', {
            sync: true,
        }),
        {
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
                stubs: {
                    'sw-iframe-renderer': true,
                },
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                },
            },
        },
    );
}

describe('module/sw-cms/elements/location-renderer/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/location-renderer');

        jest.useFakeTimers();
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
        expect(Shopware.ExtensionAPI.publishData).toHaveBeenNthCalledWith(1, {
            id: 'example_cms_element_type__config-element',
            path: 'element',
            scope: expect.anything(),
        });

        expect(Shopware.ExtensionAPI.publishData).toHaveBeenNthCalledWith(2, {
            id: 'example_cms_element_type__config-element__123456789',
            path: 'element',
            scope: expect.anything(),
        });
    });

    it('should unpublish the old publishData when elementData changes', async () => {
        const unpublishDataMock = jest.fn();

        jest.spyOn(Shopware.ExtensionAPI, 'publishData').mockImplementation(() => {
            return () => {
                unpublishDataMock();
            };
        });

        const wrapper = await createWrapper();

        await flushPromises();

        expect(unpublishDataMock).toHaveBeenCalledTimes(0);

        await wrapper.setProps({
            elementData: {
                name: 'example_cms_element_type_foo',
                appData: {
                    baseUrl: 'http://test.example-app.com',
                },
            },
        });

        await flushPromises();

        expect(unpublishDataMock).toHaveBeenCalledTimes(2);
    });
});
