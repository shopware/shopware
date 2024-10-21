/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-preview-product-slider', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-cms-product-box-preview': await wrapTestComponent('sw-cms-product-box-preview'),
                    'sw-icon': true,
                },
            },
        },
    );
}

describe('src/module/sw-cms/elements/product-slider/preview', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
