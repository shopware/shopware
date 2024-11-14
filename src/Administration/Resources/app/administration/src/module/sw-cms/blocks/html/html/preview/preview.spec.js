/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-preview-html', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-code-editor': await wrapTestComponent('sw-code-editor'),
                    'sw-icon': true,
                },
            },
        },
    );
}

describe('src/module/sw-cms/blocks/html/html/preview', () => {
    it('contains the demo value', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.demoValue).toContain('<h2>Lorem ipsum dolor</h2>');
    });

    it('renders the demo value in the HTML editor', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.html()).toContain('<h2>Lorem ipsum dolor</h2>');
    });
});
