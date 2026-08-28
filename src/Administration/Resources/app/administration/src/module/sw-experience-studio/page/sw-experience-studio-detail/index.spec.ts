import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentLayoutDraftMutationResponse } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
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
        const respondedElement: ContentElementNode = {
            id: 'element-2',
            component: 'Sw:Content:Text',
        };
        const vm = {
            layout: {
                layout: [] as ContentElementNode[],
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
        const previousLayout: ContentElementNode[] = [
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
            (response: ContentLayoutDraftMutationResponse) => response.affectedElementIds[0] ?? null,
        );

        expect(vm.requestDraftMutation).toHaveBeenCalledWith('insert', previousLayout, { type: 'Sw:Content:Text' });
        expect(pushToHistory).toHaveBeenCalledWith(previousLayout, 'element-1');
        expect(vm.selectedElementId).toBe('element-2');
        expect(vm.layout.layout).toEqual([respondedElement]);
        expect(vm.isLoading).toBe(false);
    });

    it('ignores stale mutation responses by request id', async () => {
        let resolveFirstRequest!: (response: ContentLayoutDraftMutationResponse) => void;
        const newerElement: ContentElementNode = { id: 'newer', component: 'Sw:Content:Text' };
        const vm = {
            layout: {
                layout: [] as ContentElementNode[],
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
                .mockImplementationOnce(
                    () =>
                        new Promise((resolve: (response: ContentLayoutDraftMutationResponse) => void) => {
                            resolveFirstRequest = resolve;
                        }),
                )
                .mockResolvedValueOnce({
                    layout: [newerElement],
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
            (response: ContentLayoutDraftMutationResponse) => response.affectedElementIds[0] ?? null,
        );

        await secondCall;
        resolveFirstRequest({
            layout: [{ id: 'stale', component: 'Sw:Content:Text' }],
            resolutions: {},
            diagnostics: { wellFormed: true, resolvable: true, violations: [] },
            affectedElementIds: ['stale'],
            orphaned: [],
            droppedWiring: [],
            droppedProperties: {},
        });
        await firstCall;

        expect(vm.layout.layout).toEqual([newerElement]);
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

    it('adopts the server-canonical layout returned by the save reload without client-side re-normalization', async () => {
        // Authored client-side: style as a bare scalar, no seeded default, keys in author order.
        const authoredElement: ContentElementNode = {
            component: 'Sw:Filter:Panel',
            style: { 'col-span': 6 },
            id: 'element-1',
            properties: { visibleFilterCount: 5 },
        };
        // Server-canonical: `ElementStyleNormalizer::normalizeValue()` broadcasts a breakpoint-aware
        // scalar across every `Breakpoint::values()` entry, `LayoutDefaultSeeder::seedElement()` appends
        // the `showLayoutSwitch: true` default the type declares and the author left out, and
        // `StoredElement::jsonSerialize()` fixes the key order.
        const canonicalElement: ContentElementNode = {
            id: 'element-1',
            component: 'Sw:Filter:Panel',
            properties: { visibleFilterCount: 5, showLayoutSwitch: true },
            style: { 'col-span': { xs: 6, sm: 6, md: 6, lg: 6, xl: 6, xxl: 6 } },
        };
        const reloadedLayout = {
            id: 'layout-1',
            name: 'Landing page',
            layout: [canonicalElement],
        };
        const save = jest.fn().mockResolvedValue(undefined);
        const get = jest.fn().mockResolvedValue(reloadedLayout);
        const vm = {
            layout: {
                id: 'layout-1',
                name: 'Landing page',
                layout: [authoredElement],
            } as unknown as typeof reloadedLayout,
            allowSave: true,
            layoutRootSource: 'product',
            layoutLoadCriteria: {},
            layoutRepository: { save, get },
            applyPreviewContextDefaults: jest.fn(),
            createNotificationSuccess: jest.fn(),
            $t: jest.fn().mockReturnValue('saved'),
            isCreateMode: false,
            isLoading: false,
        };

        await methods.onSave.call(vm);

        const saveCalls = save.mock.calls as unknown[][];

        expect(saveCalls[0][0]).toEqual({
            id: 'layout-1',
            name: 'Landing page',
            layout: [
                {
                    component: 'Sw:Filter:Panel',
                    style: { 'col-span': 6 },
                    id: 'element-1',
                    properties: { visibleFilterCount: 5 },
                },
            ],
        });
        expect(vm.layout).toBe(reloadedLayout);
        expect(vm.layout.layout[0]).toBe(canonicalElement);
        expect(Object.keys(vm.layout.layout[0])).toEqual([
            'id',
            'component',
            'properties',
            'style',
        ]);
        expect(vm.layout.layout[0].style).toEqual({
            'col-span': { xs: 6, sm: 6, md: 6, lg: 6, xl: 6, xxl: 6 },
        });
        expect(vm.layout.layout[0].properties).toEqual({ visibleFilterCount: 5, showLayoutSwitch: true });
    });
});
