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
            getLayoutRootSource: methods.getLayoutRootSource,
        };

        expect(methods.resolveMutationRootSource.call(vm)).toBe('product');
    });

    it('returns null rootSource when no rootSource is set', () => {
        const vm = {
            layout: {
                rootSource: null,
            },
            getLayoutRootSource: methods.getLayoutRootSource,
        };

        expect(methods.resolveMutationRootSource.call(vm)).toBeNull();
    });

    it('creates draft mutation payload with sanitized layout and rootSource', () => {
        const vm = {
            resolveMutationRootSource: () => null,
            sanitizeLayoutForWrite: (layout: unknown) => layout,
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

        expect(payload.rootSource).toBeNull();
        expect(payload.layout).toHaveLength(1);
        expect(payload.type).toBe('Sw:Content:Text');
    });

    it('derives preview entity type from layout rootSource', () => {
        const vm = {
            getLayoutRootSource: methods.getLayoutRootSource,
            resolveAssignedPreviewContext: jest.fn().mockReturnValue(null),
        };

        const previewContext = methods.resolvePreviewContext.call(vm, {
            rootSource: 'product',
        });

        expect(previewContext).toEqual({
            entityType: 'product',
            entityId: null,
            salesChannelId: null,
        });
    });

    it('loads first preview entity when assignment does not provide one', async () => {
        const vm = {
            previewEntityId: null,
            previewEntityType: 'category',
            repositoryFactory: {
                create: jest.fn().mockReturnValue({
                    search: jest.fn().mockResolvedValue({
                        first: () => ({ id: 'entity-1' }),
                    }),
                }),
            },
            defaultPreviewEntityCriteria: {},
        };

        await methods.loadDefaultPreviewEntity.call(vm);

        expect(vm.previewEntityId).toBe('entity-1');
    });

    it('moves element via structural draft mutation', async () => {
        const executeStructuralDraftMutation = jest.fn().mockResolvedValue(undefined);
        const normalizeMoveIndex = jest.fn().mockReturnValue(1);
        const vm = {
            layout: {
                layout: [
                    {
                        id: 'element-1',
                        component: 'Sw:Content:Text',
                    },
                ],
            },
            allowSave: true,
            executeStructuralDraftMutation,
            normalizeMoveIndex,
        };

        await methods.onMoveElement.call(vm, {
            elementId: 'element-1',
            newParentElementId: 'parent-1',
            newSlotName: 'main',
            newIndex: 2,
        });

        expect(executeStructuralDraftMutation).toHaveBeenCalledWith(
            'move',
            [{ id: 'element-1', component: 'Sw:Content:Text' }],
            {
                elementId: 'element-1',
                newParentId: 'parent-1',
                newSlot: 'main',
                index: 1,
            },
            expect.any(Function),
        );
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
            sanitizeLayoutForWrite: (layout: unknown) => layout,
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
            sanitizeLayoutForWrite: (layout: unknown) => layout,
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

    it('calls move mutation endpoint for move operations', async () => {
        const moveElement = jest.fn().mockResolvedValue({
            layout: [],
            resolutions: {},
            diagnostics: {
                wellFormed: true,
                resolvable: true,
                violations: [],
            },
            affectedElementIds: [],
            orphaned: [],
            droppedWiring: [],
            droppedProperties: {},
        });
        const vm = {
            draftMutationService: () => ({
                moveElement,
            }),
            createDraftMutationPayload: jest.fn().mockReturnValue({
                layout: [],
                rootSource: 'category',
                elementId: 'element-1',
                newParentId: null,
                newSlot: null,
            }),
        };

        await methods.requestDraftMutation.call(
            vm,
            'move',
            [],
            {
                elementId: 'element-1',
                newParentId: null,
                newSlot: null,
            },
        );

        expect(moveElement).toHaveBeenCalledWith({
            layout: [],
            rootSource: 'category',
            elementId: 'element-1',
            newParentId: null,
            newSlot: null,
        });
    });

    it('rejects invalid move targets from subtree cycles', () => {
        const vm = {
            layout: {
                layout: [
                    {
                        id: 'parent',
                        component: 'Sw:Layout:Container',
                        slots: {
                            main: [
                                {
                                    id: 'child',
                                    component: 'Sw:Content:Text',
                                },
                            ],
                        },
                    },
                ],
            },
            isElementInSubtree: methods.isElementInSubtree,
        };

        expect(
            methods.validateMoveTarget.call(vm, {
                elementId: 'parent',
                newParentElementId: 'child',
                newSlotName: 'main',
                newIndex: 0,
            }),
        ).toBe(false);
    });

    it('adjusts move index when reordering in same slot', () => {
        const layout = [
            {
                id: 'parent',
                component: 'Sw:Layout:Container',
                slots: {
                    main: [
                        { id: 'a', component: 'Sw:Content:Text' },
                        { id: 'b', component: 'Sw:Content:Text' },
                        { id: 'c', component: 'Sw:Content:Text' },
                    ],
                },
            },
        ];
        const vm = {
            resolveMoveTargetElements: methods.resolveMoveTargetElements,
        };

        const normalizedIndex = methods.normalizeMoveIndex.call(vm, layout, {
            elementId: 'a',
            newParentElementId: 'parent',
            newSlotName: 'main',
            newIndex: 2,
        });

        expect(normalizedIndex).toBe(1);
    });
});
