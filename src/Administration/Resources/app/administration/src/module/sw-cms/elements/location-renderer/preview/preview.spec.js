/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

const elementData = {
    name: 'foo-bar',
    label: 'Foo Bar',
    component: 'sw-cms-el-location-renderer',
    previewComponent: 'sw-cms-el-preview-location-renderer',
    configComponent: 'sw-cms-el-config-location-renderer',
    defaultConfig: {},
    appData: {
        baseUrl: 'https://example.com',
    },
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-preview-location-renderer', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-iframe-renderer': await wrapTestComponent('sw-iframe-renderer'),
                },
            },
            props: {
                elementData,
            },
        },
    );
}

describe('src/module/sw-cms/elements/location-renderer/preview', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
