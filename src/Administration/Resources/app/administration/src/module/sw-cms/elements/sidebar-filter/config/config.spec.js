/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-config-sidebar-filter', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-alert': true,
                },
            },
        },
    );
}

describe('src/module/sw-cms/elements/sidebar-filter/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/sidebar-filter');
    });

    it('mounts the component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeDefined();
    });
});
