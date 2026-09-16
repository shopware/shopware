/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-preview-manufacturer-logo', {
            sync: true,
        }),
    );
}

describe('src/module/sw-cms/elements/manufacturer-logo/preview', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/manufacturer-logo');
    });

    it('renders an image placeholder icon', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-el-preview-manufacturer-logo').exists()).toBe(true);
        expect(wrapper.find('.mt-icon.icon--regular-image').exists()).toBe(true);
    });
});
