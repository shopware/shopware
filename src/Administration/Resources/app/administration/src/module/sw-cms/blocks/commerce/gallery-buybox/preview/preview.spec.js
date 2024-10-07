/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-preview-gallery-buybox', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-cms-el-preview-buy-box': true,
                    'sw-icon': true,
                },
            },
        },
    );
}

describe('src/module/sw-cms/blocks/commerce/gallery-buybox/preview', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
