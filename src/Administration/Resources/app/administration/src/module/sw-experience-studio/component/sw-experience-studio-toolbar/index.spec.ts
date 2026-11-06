import { shallowMount } from '@vue/test-utils';
import toolbarComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-toolbar', () => {
    const config = toolbarComponent as unknown as { props: Record<string, unknown>; emits: string[] };

    it('does not expose layout-specific assignment actions', () => {
        expect(config.props.canManageAssignments).toBeUndefined();
        expect(config.emits).not.toContain('open-assignments');
    });
    it('shows the workspace switcher instead of layout navigation and history in workspace mode', async () => {
        const wrapper = shallowMount(await wrapTestComponent('sw-experience-studio-toolbar', { sync: true }), {
            props: { isWorkspace: true, layout: { name: 'Layout name' } },
            slots: { 'workspace-switcher': '<span>Selected workspace</span>' },
        });
        expect(wrapper.text()).toContain('Selected workspace');
        expect(wrapper.find('.sw-experience-studio-toolbar__back-btn').exists()).toBe(false);
        expect(wrapper.find('.sw-experience-studio-toolbar__page-name').exists()).toBe(false);
        expect(wrapper.find('.sw-experience-studio-toolbar__history-actions').exists()).toBe(false);
        wrapper.unmount();
    });
});
