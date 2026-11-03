import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentLayoutDraftMutationResponse } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import detailComponent from './index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

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

    it('stores violations returned by draft diagnosis', async () => {
        const violation = {
            code: 'invalid_mapping',
            scope: 'binding',
            severity: 'error',
            elementId: 'element-1',
            key: 'text',
            message: 'Invalid mapping',
            candidates: [],
        };
        const diagnose = jest.fn().mockResolvedValue({
            resolutions: {},
            diagnostics: {
                wellFormed: true,
                resolvable: false,
                violations: [violation],
            },
        });
        const vm = {
            layout: {
                layout: [{ id: 'element-1', component: 'Sw:Content:Text' }],
            },
            diagnostics: [],
            diagnoseRequestSequence: 0,
            latestDiagnoseRequestId: 0,
            draftMutationService: () => ({ diagnose }),
            resolveMutationRootSource: () => 'category',
        };

        await methods.diagnoseLayout.call(vm);

        expect(diagnose).toHaveBeenCalledWith({
            layout: [{ id: 'element-1', component: 'Sw:Content:Text' }],
            rootSource: 'category',
        });
        expect(vm.diagnostics).toEqual([violation]);
    });

    it('debounces diagnoses while the layout is changing', async () => {
        jest.useFakeTimers();
        const diagnose = jest.fn().mockResolvedValue({ diagnostics: { violations: [] } });
        const vm = {
            layout: { layout: [] },
            diagnostics: [],
            diagnoseRequestSequence: 0,
            latestDiagnoseRequestId: 0,
            diagnoseTimeoutId: null as number | null,
            draftMutationService: () => ({ diagnose }),
            resolveMutationRootSource: () => 'category',
            diagnoseLayout() {
                return methods.diagnoseLayout.call(this);
            },
        };

        methods.scheduleDiagnoseLayout.call(vm);
        jest.advanceTimersByTime(200);
        methods.scheduleDiagnoseLayout.call(vm);
        jest.advanceTimersByTime(799);
        expect(diagnose).not.toHaveBeenCalled();

        jest.advanceTimersByTime(1);
        await flushPromises();

        expect(diagnose).toHaveBeenCalledTimes(1);
        jest.useRealTimers();
    });

    it('creates draft mutation payload from typed layout elements and rootSource', () => {
        const element: ContentElementNode = {
            id: 'element-1',
            component: 'Sw:Content:Text',
            properties: {
                text: 'Hello',
            },
        };
        const vm = {
            resolveMutationRootSource: () => null,
        };

        const payload = methods.createDraftMutationPayload.call(vm, [element], {
            type: 'Sw:Content:Text',
        });

        expect(payload).toEqual({
            layout: [element],
            rootSource: null,
            type: 'Sw:Content:Text',
        });
    });

    it('carries attributed specification values into the draft mutation request body', () => {
        const element: ContentElementNode = {
            id: 'element-1',
            component: 'Sw:Content:Text',
            attributedSpecifications: {
                'Sw:Content:Text': 'SwagBlog',
                headline: 'SwagPromotion',
            },
        };
        const vm = {
            resolveMutationRootSource: () => null,
        };

        const payload = methods.createDraftMutationPayload.call(vm, [element], {}) as { layout: ContentElementNode[] };

        expect(payload.layout[0].attributedSpecifications).toEqual({
            'Sw:Content:Text': 'SwagBlog',
            headline: 'SwagPromotion',
        });
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
            {
                elementId: 'element-1',
                newParentId: 'parent-1',
                newSlot: 'main',
                index: 1,
            },
            expect.any(Function),
        );
    });

    it.each([
        [
            'map-property',
            { type: 'root', id: 'category', path: 'name' },
        ],
        [
            'unmap-property',
            null,
        ],
    ] as const)('changes an element mapping via the %s draft mutation', async (operation, source) => {
        const executeStructuralDraftMutation = jest.fn().mockResolvedValue(undefined);
        const layout = [{ id: 'element-1', component: 'Sw:Content:Text' }];
        const vm = {
            layout: { layout },
            executeStructuralDraftMutation,
            resolveMutationRootSource: jest.fn().mockReturnValue('category'),
            notifyMutationError: jest.fn(),
        };

        await methods.onElementMappingChange.call(vm, {
            elementId: 'element-1',
            propertyKey: 'text',
            source,
            contextType: 'single',
        });

        expect(executeStructuralDraftMutation).toHaveBeenCalledWith(
            operation,
            {
                elementId: 'element-1',
                propertyKey: 'text',
                ...(source === null ? {} : { source }),
            },
            expect.any(Function),
        );
    });

    it('records history and applies latest successful draft mutation response', async () => {
        const pushToHistory = jest.fn();
        const previousLayout: ContentElementNode[] = [
            {
                id: 'element-1',
                component: 'Sw:Content:Text',
            },
        ];
        const respondedElement: ContentElementNode = {
            id: 'element-2',
            component: 'Sw:Content:Text',
        };
        const vm = {
            layout: {
                layout: previousLayout,
            },
            allowSave: true,
            mutationQueue: Promise.resolve(),
            pendingMutationCount: 0,
            isLoading: false,
            selectedElementId: 'element-1',
            editorStore: {
                pushToHistory,
            },
            requestDraftMutation: jest.fn().mockResolvedValue({
                layout: [respondedElement],
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
        await methods.executeStructuralDraftMutation.call(
            vm,
            'insert',
            { type: 'Sw:Content:Text' },
            (response: ContentLayoutDraftMutationResponse) => response.affectedElementIds[0] ?? null,
        );

        expect(vm.requestDraftMutation).toHaveBeenCalledWith('insert', previousLayout, { type: 'Sw:Content:Text' });
        expect(pushToHistory).toHaveBeenCalledWith(previousLayout, 'element-1');
        expect(vm.selectedElementId).toBe('element-2');
        expect(vm.layout.layout).toEqual([respondedElement]);
        expect(vm.isLoading).toBe(false);
    });

    it('serializes draft mutations and uses the latest layout for each request', async () => {
        let resolveFirstRequest!: (response: ContentLayoutDraftMutationResponse) => void;
        const firstMapping: ContentElementNode = { id: 'first-mapping', component: 'Sw:Content:Text' };
        const secondMapping: ContentElementNode = { id: 'second-mapping', component: 'Sw:Content:Text' };
        const initialLayout: ContentElementNode[] = [{ id: 'initial', component: 'Sw:Content:Text' }];
        const requestDraftMutation = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve: (response: ContentLayoutDraftMutationResponse) => void) => {
                        resolveFirstRequest = resolve;
                    }),
            )
            .mockImplementationOnce((_operation: string, layout: ContentElementNode[]) =>
                Promise.resolve({
                    layout: [
                        ...layout,
                        secondMapping,
                    ],
                    resolutions: {},
                    diagnostics: { wellFormed: true, resolvable: true, violations: [] },
                    affectedElementIds: ['second-mapping'],
                    orphaned: [],
                    droppedWiring: [],
                    droppedProperties: {},
                }),
            );
        const vm = {
            layout: {
                layout: initialLayout,
            },
            allowSave: true,
            mutationQueue: Promise.resolve(),
            pendingMutationCount: 0,
            isLoading: false,
            selectedElementId: 'element-1',
            editorStore: {
                pushToHistory: jest.fn(),
            },
            requestDraftMutation,
            notifyMutationError: jest.fn(),
            extractMutationErrorCodes: jest.fn().mockReturnValue([]),
        };
        const firstCall = methods.executeStructuralDraftMutation.call(
            vm,
            'map-property',
            { propertyKey: 'first', source: { type: 'context', id: 'first' } },
            () => 'element-1',
        );
        const secondCall = methods.executeStructuralDraftMutation.call(
            vm,
            'map-property',
            { propertyKey: 'second', source: { type: 'context', id: 'second' } },
            () => 'element-1',
        );

        await Promise.resolve();
        expect(requestDraftMutation).toHaveBeenCalledTimes(1);
        resolveFirstRequest({
            layout: [firstMapping],
            resolutions: {},
            diagnostics: { wellFormed: true, resolvable: true, violations: [] },
            affectedElementIds: ['first-mapping'],
            orphaned: [],
            droppedWiring: [],
            droppedProperties: {},
        });
        await Promise.all([
            firstCall,
            secondCall,
        ]);

        expect(requestDraftMutation).toHaveBeenNthCalledWith(1, 'map-property', initialLayout, {
            propertyKey: 'first',
            source: { type: 'context', id: 'first' },
        });
        expect(requestDraftMutation).toHaveBeenNthCalledWith(2, 'map-property', [firstMapping], {
            propertyKey: 'second',
            source: { type: 'context', id: 'second' },
        });
        expect(vm.layout.layout).toEqual([
            firstMapping,
            secondMapping,
        ]);
        expect(vm.isLoading).toBe(false);
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

        await methods.requestDraftMutation.call(vm, 'move', [], {
            elementId: 'element-1',
            newParentId: null,
            newSlot: null,
        });

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
