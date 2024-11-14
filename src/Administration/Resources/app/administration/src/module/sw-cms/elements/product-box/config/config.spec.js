/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-config-product-box', { sync: true }), {
        props: {
            element: {},
        },
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
            stubs: {
                'sw-entity-single-select': true,
                'sw-product-variant-info': true,
                'sw-select-field': true,
                'sw-select-result': true,
            },
        },
    });
}

describe('src/module/sw-cms/elements/product-box/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/product-box');
    });

    it('mounts the component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeDefined();
    });
});
