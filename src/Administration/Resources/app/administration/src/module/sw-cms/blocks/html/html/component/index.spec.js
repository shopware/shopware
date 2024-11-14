import { mount } from '@vue/test-utils';

/**
 * @package buyers-experience
 */
async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-block-html', {
            sync: true,
        }),
        {
            slots: {
                content: '<div>Test</div>',
            },
        },
    );
}

describe('src/module/sw-cms/blocks/html/html/component/index.ts', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render slot content', () => {
        expect(wrapper.html()).toContain('<div>Test</div>');
    });
});
