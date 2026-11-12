import toolbarComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-toolbar', () => {
    const methods = (toolbarComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    it('emits open-assignments when the assignments button is clicked', () => {
        const emit = jest.fn();

        methods.onOpenAssignments.call({ $emit: emit });

        expect(emit).toHaveBeenCalledWith('open-assignments');
    });
});
