/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-preview-image-gallery', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-cms-preview-image-slider': true,
                    'sw-icon': true,
                },
            },
        },
    );
}

describe('src/module/sw-cms/blocks/image/image-gallery/preview', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
