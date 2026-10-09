/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-preview-product-name', {
            sync: true,
        }),
    );
}

describe('src/module/sw-cms/elements/product-name/preview', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/product-name');
    });

    it('renders the product name label as heading', async () => {
        const wrapper = await createWrapper();

        const heading = wrapper.find('.sw-cms-el-preview-product-name__heading');
        expect(heading.exists()).toBe(true);
        expect(heading.text()).toBe('sw-cms.elements.productHeading.name.label');
    });
});
