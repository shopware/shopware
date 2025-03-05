/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-config-category-navigation', {
            sync: true,
        }),
        {
            global: {
                stubs: {},
            },
        },
    );
}

describe('src/module/sw-cms/elements/sidebar-category-navigation/config', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
