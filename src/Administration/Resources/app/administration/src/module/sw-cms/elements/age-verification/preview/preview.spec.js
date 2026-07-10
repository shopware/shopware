/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-preview-age-verification', {
            sync: true,
        }),
    );
}

describe('src/module/sw-cms/elements/age-verification/preview', () => {
    it('renders the age verification preview', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.classes()).toContain('sw-cms-el-preview-age-verification');
        expect(wrapper.text()).toContain('18+');
    });
});
