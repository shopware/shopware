/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-category-navigation', { sync: true }), {
        props: {
            element: {

            },
        },
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
        },
    });
}

describe('src/module/sw-cms/elements/sidebar-category-navigation/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/sidebar-category-navigation');
    });

    it('mounts the component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeDefined();
    });
});
