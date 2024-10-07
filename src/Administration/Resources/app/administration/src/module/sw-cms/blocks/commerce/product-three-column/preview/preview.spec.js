/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-preview-product-three-column', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-cms-product-box-preview': true,
                },
            },
        },
    );
}

describe('src/module/sw-cms/blocks/commerce/product-three-column/preview', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
