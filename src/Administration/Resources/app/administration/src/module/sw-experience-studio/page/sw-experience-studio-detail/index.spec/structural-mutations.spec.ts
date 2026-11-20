import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentLayoutDraftMutationResponse } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import detailComponent from '../index';

describe('Experience Studio structural draft mutations', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

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
        const layout = { id: 'layout-1', layout: previousLayout };
        const vm = {
            layout,
            openLayouts: [{ layout, selectedElementId: 'element-1' }],
            resolveMutationRootSource: () => null,
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

        expect(vm.requestDraftMutation).toHaveBeenCalledWith('insert', previousLayout, { type: 'Sw:Content:Text' }, null);
        expect(pushToHistory).toHaveBeenCalledWith(previousLayout, 'element-1', 'layout-1');
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
        const layout = { id: 'layout-1', layout: initialLayout };
        const vm = {
            layout,
            openLayouts: [{ layout, selectedElementId: 'element-1' }],
            resolveMutationRootSource: () => null,
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

        expect(requestDraftMutation).toHaveBeenNthCalledWith(
            1,
            'map-property',
            initialLayout,
            {
                propertyKey: 'first',
                source: { type: 'context', id: 'first' },
            },
            null,
        );
        expect(requestDraftMutation).toHaveBeenNthCalledWith(
            2,
            'map-property',
            [firstMapping],
            {
                propertyKey: 'second',
                source: { type: 'context', id: 'second' },
            },
            null,
        );
        expect(vm.layout.layout).toEqual([
            firstMapping,
            secondMapping,
        ]);
        expect(vm.isLoading).toBe(false);
    });
});
