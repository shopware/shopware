/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

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
            stubs: {
                'sw-iframe-renderer': true,
            },
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
        },
    });
}


describe('module/sw-cms/elements/location-renderer/config', () => {
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

    it('should add the elementId to iFrame url', async () => {
        const wrapper = await createWrapper();

        const iFrame = wrapper.find('sw-iframe-renderer-stub');

        expect(iFrame.attributes().src).toBe('http://test.example-app.com/?elementId=123456789');
    });
});
