/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-preview-html', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-code-editor': await wrapTestComponent('sw-code-editor'),
                },
            },
        },
    );
}

describe('src/module/sw-cms/elements/html/preview', () => {
    it('renders the demo value in the HTML editor', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.demoValue).toContain('<h2>Lorem ipsum</h2>');
        expect(wrapper.html()).toContain('<h2>Lorem ipsum</h2>');
    });
});
