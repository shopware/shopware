/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-preview-image-gallery', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-cms-el-preview-image-slider': await wrapTestComponent('sw-cms-el-preview-image-slider'),
                },
            },
        },
    );
}

describe('src/module/sw-cms/elements/image-gallery/preview', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
