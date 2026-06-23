import detailComponent from './index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    it('starts inline session for text elements', () => {
        const vm = {
            selectedElementId: null as string | null,
            inlineEditSession: null,
            findElementById: jest.fn().mockReturnValue({
                id: 'element-1',
                component: 'content:text',
                properties: { text: '<p>Initial</p>' },
            }),
            isTextElement: jest.fn().mockReturnValue(true),
            getElementTextValue: jest.fn().mockReturnValue('<p>Initial</p>'),
        };

        methods.onInlineEditStart.call(vm, {
            elementId: 'element-1',
        });

        expect(vm.selectedElementId).toBe('element-1');
        expect(vm.inlineEditSession).toEqual({
            elementId: 'element-1',
            originalValue: '<p>Initial</p>',
            draftValue: '<p>Initial</p>',
            isEditing: true,
        });
    });

    it('commits inline session only when value changed', () => {
        const applyLayoutMutation = jest.fn();
        const clearInlineEditSession = jest.fn();
        const vm = {
            inlineEditSession: {
                elementId: 'element-1',
                originalValue: '<p>Before</p>',
                draftValue: '<p>After</p>',
                isEditing: true,
            },
            clearInlineEditSession,
            applyLayoutMutation,
        };

        methods.onInlineEditCommit.call(vm, {
            elementId: 'element-1',
            value: '<p>Before</p>',
        });
        expect(applyLayoutMutation).not.toHaveBeenCalled();

        vm.inlineEditSession = {
            elementId: 'element-1',
            originalValue: '<p>Before</p>',
            draftValue: '<p>After</p>',
            isEditing: true,
        };

        methods.onInlineEditCommit.call(vm, {
            elementId: 'element-1',
            value: '<p>After</p>',
        });
        expect(applyLayoutMutation).toHaveBeenCalledTimes(1);
    });

    it('clears inline session on cancel for matching element', () => {
        const clearInlineEditSession = jest.fn();
        const vm = {
            inlineEditSession: {
                elementId: 'element-1',
                originalValue: '<p>Before</p>',
                draftValue: '<p>Before</p>',
                isEditing: true,
            },
            clearInlineEditSession,
        };

        methods.onInlineEditCancel.call(vm, { elementId: 'element-1' });
        expect(clearInlineEditSession).toHaveBeenCalledTimes(1);
    });
});
