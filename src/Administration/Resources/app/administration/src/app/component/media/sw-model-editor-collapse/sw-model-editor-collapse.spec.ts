/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper(props: { title: string; expandOnLoading?: boolean } = { title: 'Test Title' }) {
    return mount(await wrapTestComponent('sw-model-editor-collapse', { sync: true }), {
        props,
        global: {
            stubs: {
                'mt-icon': {
                    template: '<span class="mt-icon" />',
                },
            },
        },
    });
}

describe('src/app/component/media/sw-model-editor-collapse', () => {
    it('should mount successfully', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.exists()).toBe(true);
    });

    it('should render the title', async () => {
        const wrapper = await createWrapper({ title: 'My Section' });

        expect(wrapper.find('.sw-model-editor-collapse__title').text()).toBe('My Section');
    });

    it('should be collapsed by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-collapse__content').exists()).toBe(false);
    });

    it('should expand when the header is clicked', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-collapse__header').trigger('click');

        expect(wrapper.find('.sw-collapse__content').exists()).toBe(true);
    });

    it('should collapse again on second header click', async () => {
        const wrapper = await createWrapper();

        const header = wrapper.find('.sw-collapse__header');
        await header.trigger('click');
        expect(wrapper.find('.sw-collapse__content').exists()).toBe(true);

        await header.trigger('click');
        expect(wrapper.find('.sw-collapse__content').exists()).toBe(false);
    });

    it('should start expanded when expandOnLoading is true', async () => {
        const wrapper = await createWrapper({ title: 'Expanded', expandOnLoading: true });

        expect(wrapper.find('.sw-collapse__content').exists()).toBe(true);
    });

    describe('icon visibility classes', () => {
        it('should show expand icon and hide collapse icon when collapsed', async () => {
            const wrapper = await createWrapper();
            const buttons = wrapper.findAll('.sw-model-editor-collapse__button');

            const expandIcon = buttons[0];
            const collapseIcon = buttons[1];

            expect(expandIcon.classes()).not.toContain('is--hidden');
            expect(collapseIcon.classes()).toContain('is--hidden');
        });

        it('should hide expand icon and show collapse icon when expanded', async () => {
            const wrapper = await createWrapper();

            await wrapper.find('.sw-collapse__header').trigger('click');

            const buttons = wrapper.findAll('.sw-model-editor-collapse__button');
            const expandIcon = buttons[0];
            const collapseIcon = buttons[1];

            expect(expandIcon.classes()).toContain('is--hidden');
            expect(collapseIcon.classes()).not.toContain('is--hidden');
        });
    });

    it('should expand on Enter keydown', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-collapse__header').trigger('keydown.enter');

        expect(wrapper.find('.sw-collapse__content').exists()).toBe(true);
    });
});
