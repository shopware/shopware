/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-preview-product-box', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-cms-product-box-preview': await wrapTestComponent('sw-cms-product-box-preview'),
                },
            },
        },
    );
}

describe('src/module/sw-cms/elements/product-box/preview', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
