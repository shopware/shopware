import 'src/app/component/structure/sw-skip-link';
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        {
            template: `
            <sw-skip-link />
            <main id="main" tabindex="-1"></main>`,
        },
        {
            global: {
                stubs: {
                    'sw-skip-link': await wrapTestComponent('sw-skip-link'),
                },
            },
            attachTo: document.body,
        },
    );
}

describe('src/app/component/structure/sw-skip-link/index.ts', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should handle focus class on focus and blur events', async () => {
        const skipLink = wrapper.find('.sw-skip-link');

        await skipLink.trigger('focus');

        expect(skipLink.classes()).toContain('sw-skip-link__focussed');

        await skipLink.trigger('blur');

        expect(skipLink.classes()).not.toContain('sw-skip-link__focussed');
    });

    it('should focus element with id main on click', async () => {
        const skipLink = wrapper.find('.sw-skip-link');

        await skipLink.trigger('click');

        expect(document.activeElement.id).toBe('main');
    });
});
