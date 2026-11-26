import { shallowMount } from '@vue/test-utils';

async function createWrapper() {
    return shallowMount(await wrapTestComponent('sw-experience-studio-layout-tree', { sync: true }), {
        attachTo: document.body,
        global: { stubs: { 'sw-experience-studio-preview-list': true } },
        props: {
            items: [{ id: 'layout', name: 'Category layout', isDirty: true, saveError: null, canManageAssignments: true }],
            frames: [{ id: 'desktop', layout: { id: 'layout' }, isActive: true }],
        },
    });
}

describe('Experience Studio layout tree', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('collapses and expands frames without navigating or closing the layout', async () => {
        wrapper = await createWrapper();
        const toggle = wrapper.get('.sw-experience-studio-layout-tree__toggle');
        const frames = wrapper.get('sw-experience-studio-preview-list-stub');

        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(toggle.attributes('aria-controls')).toBe(frames.attributes('id'));
        expect(frames.isVisible()).toBe(true);
        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(wrapper.get('sw-experience-studio-preview-list-stub').isVisible()).toBe(false);
        expect(wrapper.emitted('select-layout')).toBeUndefined();
        expect(wrapper.emitted('close-layout')).toBeUndefined();
        await wrapper.get('.sw-experience-studio-layout-tree__toggle').trigger('click');
        expect(wrapper.get('sw-experience-studio-preview-list-stub').isVisible()).toBe(true);
    });

    it('keeps layout selection, assignment, and closing separate from preview actions', async () => {
        wrapper = await createWrapper();
        await wrapper.get('.sw-experience-studio-layout-tree__select').trigger('click');
        await wrapper.get('.sw-experience-studio-layout-tree__assignments').trigger('click');
        await wrapper.get('.sw-experience-studio-layout-tree__close').trigger('click');

        expect(wrapper.emitted('select-layout')).toEqual([['layout']]);
        expect(wrapper.emitted('assign')).toEqual([['layout']]);
        expect(wrapper.emitted('close-layout')).toEqual([['layout']]);
        expect(wrapper.findAll('.sw-experience-studio-layout-tree__dirty-indicator')).toHaveLength(1);
    });
});
