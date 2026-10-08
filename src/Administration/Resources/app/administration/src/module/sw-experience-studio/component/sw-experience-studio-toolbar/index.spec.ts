import toolbarComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-toolbar', () => {
    const config = toolbarComponent as unknown as { props: Record<string, unknown>; emits: string[] };

    it('does not expose layout-specific assignment actions', () => {
        expect(config.props.canManageAssignments).toBeUndefined();
        expect(config.emits).not.toContain('open-assignments');
    });
});
