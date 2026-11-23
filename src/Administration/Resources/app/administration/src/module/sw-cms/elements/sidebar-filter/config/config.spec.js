/**
 * @sw-package discovery
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
                stubs: {},
            },
        },
    );
}

describe('src/module/sw-cms/elements/sidebar-filter/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/sidebar-filter');
    });

    it('should point to the listing element for the filter settings', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.text()).toBe('sw-cms.elements.general.config.infoText.listingElement');
    });
});
