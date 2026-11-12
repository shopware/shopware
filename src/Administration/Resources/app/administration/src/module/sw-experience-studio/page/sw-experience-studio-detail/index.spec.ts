import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentLayoutDraftMutationResponse } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import detailComponent from './index';

const SYSTEM_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
const GERMAN_LANGUAGE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

describe('module/sw-experience-studio/page/sw-experience-studio-detail', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
    const computed = (detailComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;

    it('offers layout assignments only for saved product and category layouts', () => {
        expect(computed.canManageAssignments.call({ isCreateMode: false, layoutRootSource: 'product' })).toBe(true);
        expect(computed.canManageAssignments.call({ isCreateMode: false, layoutRootSource: 'category' })).toBe(true);
        expect(computed.canManageAssignments.call({ isCreateMode: true, layoutRootSource: 'category' })).toBe(false);
        expect(computed.canManageAssignments.call({ isCreateMode: false, layoutRootSource: 'landing_page' })).toBe(false);
        expect(computed.canManageAssignments.call({ isCreateMode: false, layoutRootSource: null })).toBe(false);
    });

    const elementTypeStoreFor = (translatable: boolean, propertyKey: string) => ({
        getByName: () => ({
            properties: {
                [propertyKey]: {
                    translatable,
                },
            },
        }),
    });

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

    const propertyWriteVm = (element: ContentElementNode, propertyKey: string, translatable: boolean) => ({
        layout: {
            layout: [element],
        },
        allowSave: true,
        selectedElementId: 'element-1' as string | null,
        showDiagnostics: false,
        editorStore: {
            pushToHistory: jest.fn(),
        },
        elementTypeStore: elementTypeStoreFor(translatable, propertyKey),
        isTranslatableProperty: methods.isTranslatableProperty,
        findElementById: () => element,
        writeElementPropertyValue: methods.writeElementPropertyValue,
        applyLayoutMutation: methods.applyLayoutMutation,
        scheduleDiagnoseLayout: jest.fn(),
        draftMutationService: jest.fn(),
        notifyMutationError: methods.notifyMutationError,
        createNotificationError: jest.fn(),
        $t: (snippet: string) => `translated:${snippet}`,
    });

    const textElement = (properties?: Record<string, unknown>): ContentElementNode => ({
        id: 'element-1',
        component: 'Sw:Content:Text',
        ...(properties === undefined ? {} : { properties }),
    });

    it.each([
        ['a string', 'Hello'],
        ['a number', 2],
        ['a boolean', false],
    ])(
        'writes %s settings value of a translatable property into the local layout as the system-language entry without a request',
        (_label, value) => {
            const vm = propertyWriteVm(textElement(), 'text', true);

            methods.onElementSettingsChange.call(vm, {
                elementId: 'element-1',
                propertyKey: 'text',
                value,
            });

            expect(vm.layout.layout[0].properties).toEqual({
                text: {
                    [SYSTEM_LANGUAGE_ID]: value,
                },
            });
            expect(vm.draftMutationService).not.toHaveBeenCalled();
        },
    );

    it('keeps the other language entries of a translatable property on a settings change', () => {
        const vm = propertyWriteVm(
            textElement({
                text: {
                    [SYSTEM_LANGUAGE_ID]: 'Hello',
                    [GERMAN_LANGUAGE_ID]: 'Hallo',
                },
            }),
            'text',
            true,
        );

        methods.onElementSettingsChange.call(vm, {
            elementId: 'element-1',
            propertyKey: 'text',
            value: 'Hello again',
        });

        expect(vm.layout.layout[0].properties).toEqual({
            text: {
                [SYSTEM_LANGUAGE_ID]: 'Hello again',
                [GERMAN_LANGUAGE_ID]: 'Hallo',
            },
        });
    });

    it('records a translatable settings change in the undo history', () => {
        const vm = propertyWriteVm(textElement({ text: { [SYSTEM_LANGUAGE_ID]: 'Hello' } }), 'text', true);
        const previousLayout = vm.layout.layout;

        methods.onElementSettingsChange.call(vm, {
            elementId: 'element-1',
            propertyKey: 'text',
            value: 'Hello again',
        });

        expect(vm.editorStore.pushToHistory).toHaveBeenCalledWith(previousLayout, 'element-1');
        expect(previousLayout[0].properties).toEqual({ text: { [SYSTEM_LANGUAGE_ID]: 'Hello' } });
    });

    it.each([
        ['an object', { text: 'Hello again' }],
        ['null', null],
    ])(
        'notifies the generic mutation error and keeps the layout for %s settings value of a translatable property',
        (_label, value) => {
            const vm = propertyWriteVm(textElement({ text: { [SYSTEM_LANGUAGE_ID]: 'Hello' } }), 'text', true);
            const previousLayout = vm.layout.layout;

            methods.onElementSettingsChange.call(vm, {
                elementId: 'element-1',
                propertyKey: 'text',
                value,
            });

            expect(vm.createNotificationError).toHaveBeenCalledWith({
                message: 'translated:sw-experience-studio.detail.mutationErrors.generic',
            });
            expect(vm.layout.layout).toBe(previousLayout);
            expect(vm.editorStore.pushToHistory).not.toHaveBeenCalled();
        },
    );

    it('writes a settings change of a non-translatable property into the local layout as the plain value', () => {
        const vm = propertyWriteVm(
            {
                id: 'element-1',
                component: 'Sw:Content:Headline',
                properties: {
                    headline: 'Hello',
                },
            },
            'headline',
            false,
        );

        methods.onElementSettingsChange.call(vm, {
            elementId: 'element-1',
            propertyKey: 'headline',
            value: 'Hello again',
        });

        expect(vm.layout.layout[0].properties).toEqual({ headline: 'Hello again' });
        expect(vm.draftMutationService).not.toHaveBeenCalled();
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
            requestDraftMutation: jest.fn().mockResolvedValue(mutationResponse([respondedElement], ['element-2'])),
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
        const moveElement = jest.fn().mockResolvedValue(mutationResponse([]));
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
