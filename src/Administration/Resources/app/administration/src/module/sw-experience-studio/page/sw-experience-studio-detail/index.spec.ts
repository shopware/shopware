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

    it('uses layout rootSource for draft mutation payloads', () => {
        const vm = {
            layout: {
                rootSource: 'product',
            },
        };

        expect(methods.resolveMutationRootSource.call(vm)).toBe('product');
    });

    it('falls back to category rootSource for new layouts', () => {
        const vm = {
            layout: {
                rootSource: null,
            },
        };

        expect(methods.resolveMutationRootSource.call(vm)).toBe('category');
    });

    it('creates draft mutation payload with sanitized layout and rootSource', () => {
        const vm = {
            resolveMutationRootSource: () => 'category',
        };

        const payload = methods.createDraftMutationPayload.call(
            vm,
            [
                {
                    id: 'element-1',
                    component: 'Sw:Content:Text',
                    properties: {
                        text: 'Hello',
                    },
                },
            ],
            {
                type: 'Sw:Content:Text',
            },
        );

        expect(payload.rootSource).toBe('category');
        expect(payload.layout).toHaveLength(1);
        expect(payload.type).toBe('Sw:Content:Text');
    });

    it('records history and applies latest successful draft mutation response', async () => {
        const pushToHistory = jest.fn();
        const vm = {
            layout: {
                layout: [],
            },
            allowSave: true,
            mutationRequestSequence: 0,
            latestMutationRequestId: 0,
            isLoading: false,
            selectedElementId: 'element-1',
            editorStore: {
                pushToHistory,
            },
            requestDraftMutation: jest.fn().mockResolvedValue({
                layout: [
                    {
                        id: 'element-2',
                        component: 'Sw:Content:Text',
                    },
                ],
                resolutions: {},
                diagnostics: {
                    wellFormed: true,
                    resolvable: true,
                    violations: [],
                },
                affectedElementIds: ['element-2'],
                orphaned: [],
                droppedWiring: [],
                droppedProperties: {},
            }),
            notifyMutationError: jest.fn(),
            extractMutationErrorCodes: jest.fn().mockReturnValue([]),
        };
        const previousLayout = [
            {
                id: 'element-1',
                component: 'Sw:Content:Text',
            },
        ];

        await methods.executeStructuralDraftMutation.call(
            vm,
            'insert',
            previousLayout,
            { type: 'Sw:Content:Text' },
            (response: { affectedElementIds: string[] }) => response.affectedElementIds[0] ?? null,
        );

        expect(vm.requestDraftMutation).toHaveBeenCalledWith('insert', previousLayout, { type: 'Sw:Content:Text' });
        expect(pushToHistory).toHaveBeenCalledWith(previousLayout, 'element-1');
        expect(vm.selectedElementId).toBe('element-2');
        expect(vm.layout.layout).toHaveLength(1);
        expect(vm.isLoading).toBe(false);
    });

    it('ignores stale mutation responses by request id', async () => {
        let resolveFirstRequest: ((value: unknown) => void) | null = null;
        const vm = {
            layout: {
                layout: [],
            },
            allowSave: true,
            mutationRequestSequence: 0,
            latestMutationRequestId: 0,
            isLoading: false,
            selectedElementId: 'element-1',
            editorStore: {
                pushToHistory: jest.fn(),
            },
            requestDraftMutation: jest
                .fn()
                .mockImplementationOnce(() => new Promise((resolve) => {
                    resolveFirstRequest = resolve;
                }))
                .mockResolvedValueOnce({
                    layout: [{ id: 'newer', component: 'Sw:Content:Text' }],
                    resolutions: {},
                    diagnostics: { wellFormed: true, resolvable: true, violations: [] },
                    affectedElementIds: ['newer'],
                    orphaned: [],
                    droppedWiring: [],
                    droppedProperties: {},
                }),
            notifyMutationError: jest.fn(),
            extractMutationErrorCodes: jest.fn().mockReturnValue([]),
        };
        const firstCall = methods.executeStructuralDraftMutation.call(
            vm,
            'insert',
            [{ id: 'first', component: 'Sw:Content:Text' }],
            { type: 'Sw:Content:Text' },
            () => 'first',
        );
        const secondCall = methods.executeStructuralDraftMutation.call(
            vm,
            'insert',
            [{ id: 'second', component: 'Sw:Content:Text' }],
            { type: 'Sw:Content:Text' },
            (response: { affectedElementIds: string[] }) => response.affectedElementIds[0] ?? null,
        );

        await secondCall;
        resolveFirstRequest?.({
            layout: [{ id: 'stale', component: 'Sw:Content:Text' }],
            resolutions: {},
            diagnostics: { wellFormed: true, resolvable: true, violations: [] },
            affectedElementIds: ['stale'],
            orphaned: [],
            droppedWiring: [],
            droppedProperties: {},
        });
        await firstCall;

        expect(vm.layout.layout[0].id).toBe('newer');
    });
});
