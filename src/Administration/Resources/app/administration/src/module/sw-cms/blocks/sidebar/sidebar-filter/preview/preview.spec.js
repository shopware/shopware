/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-preview-sidebar-filter', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-icon': true,
                },
            },
        },
    );
}

describe('src/module/sw-cms/blocks/sidebar/sidebar-filter/preview', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
