import { shallowMount } from '@vue/test-utils';
import toolbarComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-toolbar', () => {
    const config = toolbarComponent as unknown as { props: Record<string, unknown>; emits: string[] };

    it('does not expose layout-specific assignment actions', () => {
        expect(config.props.canManageAssignments).toBeUndefined();
        expect(config.emits).not.toContain('open-assignments');
    });
    it('always shows the workspace switcher and keeps entity selection and history in the frames', async () => {
        const wrapper = shallowMount(await wrapTestComponent('sw-experience-studio-toolbar', { sync: true }), {
            slots: { 'workspace-switcher': '<span>Selected workspace</span>' },
            global: {
                stubs: {
                    'sw-entity-single-select': {
                        name: 'sw-entity-single-select',
                        props: ['entity'],
                        template: '<div />',
                    },
                },
            },
        });
        expect(wrapper.text()).toContain('Selected workspace');
        expect(wrapper.find('.sw-experience-studio-toolbar__back-btn').exists()).toBe(false);
        expect(wrapper.find('.sw-experience-studio-toolbar__page-name').exists()).toBe(false);
        expect(wrapper.find('.sw-experience-studio-toolbar__history-actions').exists()).toBe(false);
        expect(wrapper.find('.sw-experience-studio-toolbar__entity-select').exists()).toBe(false);
        const selectors = wrapper.findAllComponents({ name: 'sw-entity-single-select' });
        expect(selectors).toHaveLength(1);
        expect(selectors[0].props('entity')).toBe('sales_channel');
        wrapper.unmount();
    });
});
