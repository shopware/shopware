import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-preview-html', {
        sync: true,
    }));
}

describe('src/module/sw-cms/blocks/html/html/preview/index.ts', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('contains the demo value', () => {
        expect(wrapper.vm.demoValue).toContain('<h2>Lorem ipsum dolor</h2>');
    });

    it('renders the demo value in the HTML editor', () => {
        expect(wrapper.html()).toContain('<h2>Lorem ipsum dolor</h2>');
    });
});
