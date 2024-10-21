/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-product-slider', { sync: true }), {
        props: {
            element: {},
        },
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
            stubs: {
                'sw-cms-el-product-box': await wrapTestComponent('sw-cms-el-product-box'),
                'sw-icon': true,
            },
        },
    });
}

describe('src/module/sw-cms/elements/product-slider/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/product-slider');
    });

    it('mounts the component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeDefined();
    });
});
