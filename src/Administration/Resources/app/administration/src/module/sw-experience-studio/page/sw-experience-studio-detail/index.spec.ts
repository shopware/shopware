import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentLayoutDraftMutationResponse } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import detailComponent from './index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    const mutationResponse = (
        layout: ContentElementNode[],
        affectedElementIds: string[] = [],
    ): ContentLayoutDraftMutationResponse => ({
        layout,
        resolutions: {},
        diagnostics: {
            wellFormed: true,
            resolvable: true,
            violations: [],
        },
        affectedElementIds,
        orphaned: [],
        droppedWiring: [],
        droppedProperties: {},
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
            getLayoutRootSource: () => 'category',
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
            getLayoutRootSource: () => 'category',
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

    // Regression guard: an available entity root source continues to provide a preview context.
    it('derives preview entity type from layout rootSource', () => {
        const vm = {
            availableLayoutTypes: ['product'],
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

    it.each([
        'header',
        'footer',
        'none',
        'swag-sidebar',
    ])('returns no preview context for the non-entity root source %s', (rootSource) => {
        const vm = {
            availableLayoutTypes: ['product'],
            getLayoutRootSource: methods.getLayoutRootSource,
            resolveAssignedPreviewContext: jest.fn().mockReturnValue(null),
        };

        expect(methods.resolvePreviewContext.call(vm, { rootSource })).toBeNull();
    });

    // Regression guard: an entity root source keeps the preview context it had before root-source filtering.
    it('keeps the preview context for an available entity root source', () => {
        const vm = {
            availableLayoutTypes: ['product'],
            getLayoutRootSource: methods.getLayoutRootSource,
            resolveAssignedPreviewContext: jest.fn().mockReturnValue(null),
        };

        expect(methods.resolvePreviewContext.call(vm, { rootSource: 'product' })).toEqual({
            entityType: 'product',
            entityId: null,
            salesChannelId: null,
        });
    });

    it('does not create a repository while loading the default preview entity for a non-entity root source', async () => {
        const repositoryFactory = { create: jest.fn() };
        const vm = {
            availableLayoutTypes: ['product'],
            previewEntityId: null,
            layout: { id: 'layout-header', rootSource: 'header' },
            workspaceStore: { activeWorkspace: { id: 'workspace' } },
            repositoryFactory,
            defaultPreviewEntityCriteria: {},
            getLayoutRootSource: methods.getLayoutRootSource,
            getFirstAssociationEntry: methods.getFirstAssociationEntry,
            resolveAssignedPreviewContext: methods.resolveAssignedPreviewContext,
            resolvePreviewContext: methods.resolvePreviewContext,
        };
        Object.defineProperties(vm, {
            resolvedPreviewContext: {
                get: () => methods.resolvePreviewContext.call(vm, vm.layout),
            },
            previewEntityType: {
                get: () => {
                    const previewContext = methods.resolvePreviewContext.call(vm, vm.layout) as {
                        entityType: string;
                    } | null;

                    return previewContext?.entityType ?? null;
                },
            },
        });

        await methods.loadDefaultPreviewEntity.call(vm);

        expect(repositoryFactory.create).not.toHaveBeenCalled();
    });

    it('marks a new layout as loading while waiting for entity types and applies its assigned preview context', async () => {
        let resolveLayoutTypes: () => void;
        const layoutTypesRequest = new Promise<void>((resolve) => {
            resolveLayoutTypes = resolve;
        });
        const layout = {
            id: 'layout-product',
            rootSource: 'product',
            productContentLayouts: [{ productId: 'product-id', salesChannelId: 'sales-channel-id' }],
        };
        const vm = {
            layoutTypesRequest,
            availableLayoutTypes: [] as string[],
            openLayouts: [] as unknown[],
            activeLayoutId: null,
            isLoading: false,
            isLayoutPickerOpen: true,
            layoutRepository: { get: jest.fn().mockResolvedValue(layout) },
            layoutLoadCriteria: {},
            createOpenLayoutState: jest.fn().mockReturnValue({ id: layout.id, layout }),
            activateLayout: jest.fn(),
            applyPreviewContextDefaults: methods.applyPreviewContextDefaults,
            loadDefaultPreviewEntity: jest.fn().mockResolvedValue(undefined),
            syncActiveLayoutState: jest.fn(),
            diagnoseLayout: jest.fn(),
            $nextTick: jest.fn().mockResolvedValue(undefined),
            $refs: { experienceStudioCanvas: { fitCanvasToFrame: jest.fn(), fitCanvasToFrames: jest.fn() } },
            createNotificationError: jest.fn(),
            $t: (key: string) => key,
            previewEntityId: null as string | null,
            previewSalesChannelId: null as string | null,
            getLayoutRootSource: methods.getLayoutRootSource,
            getFirstAssociationEntry: methods.getFirstAssociationEntry,
            resolveAssignedPreviewContext: methods.resolveAssignedPreviewContext,
            resolvePreviewContext: methods.resolvePreviewContext,
        };
        Object.defineProperties(vm, {
            resolvedPreviewContext: {
                get: () => methods.resolvePreviewContext.call(vm, layout),
            },
        });

        const open = methods.openLayoutById.call(vm, layout.id);
        await Promise.resolve();
        expect(vm.isLoading).toBe(true);
        expect(vm.layoutRepository.get).not.toHaveBeenCalled();

        vm.availableLayoutTypes = ['product'];
        resolveLayoutTypes!();
        await open;

        expect(vm.layoutRepository.get).toHaveBeenCalledWith(layout.id, Shopware.Context.api, vm.layoutLoadCriteria);
        expect(vm.previewEntityId).toBe('product-id');
        expect(vm.previewSalesChannelId).toBe('sales-channel-id');
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

    it('calls move mutation endpoint for move operations', async () => {
        const moveElement = jest.fn().mockResolvedValue(mutationResponse([]));
        const vm = {
            draftMutationService: () => ({
                moveElement,
            }),
            resolveMutationRootSource: () => null,
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
