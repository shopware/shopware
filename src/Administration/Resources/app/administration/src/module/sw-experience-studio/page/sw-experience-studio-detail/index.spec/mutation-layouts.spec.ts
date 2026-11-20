import type { ContentElementNode } from 'src/core/service/content-element.types';
import type {
    ContentLayoutDraftDuplicatePayload,
    ContentLayoutDraftMutationResponse,
    ContentSystemViolation,
} from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import detailComponent from '../index';
import '../../../store/experience-studio-editor.store';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason: unknown) => void;
    const promise = new Promise<T>((resolvePromise, rejectPromise) => {
        resolve = resolvePromise;
        reject = rejectPromise;
    });

    return { promise, resolve, reject };
}

function response(layout: ContentElementNode[], affectedElementId: string): ContentLayoutDraftMutationResponse {
    return {
        layout,
        affectedElementIds: [affectedElementId],
        diagnostics: { wellFormed: true, resolvable: true, violations: [] },
        resolutions: {},
        orphaned: [],
        droppedWiring: [],
        droppedProperties: {},
    };
}

function createEditor(duplicateElement: jest.Mock) {
    const layouts = [
        { id: 'layout-a', name: 'A', rootSource: 'product', layout: [{ id: 'a', component: 'Sw:Content:Text' }] },
        { id: 'layout-b', name: 'B', rootSource: 'category', layout: [{ id: 'b', component: 'Sw:Content:Text' }] },
    ];
    const editorStore = Shopware.Store.get('experienceStudioEditor');
    editorStore.initialize('layout-a');
    const editor = {
        layout: layouts[0],
        activeLayoutId: 'layout-a',
        openLayouts: layouts.map((layout) => ({
            id: layout.id,
            layout,
            selectedElementId: null as string | null,
            viewport: 'desktop',
            previewEntityId: null,
            settingsWidth: 320,
        })),
        allowSave: true,
        selectedElementId: 'a' as string | null,
        currentViewport: 'desktop',
        previewEntityId: null,
        elementSettingsWidth: 320,
        isLoading: false,
        pendingMutationCount: 0,
        mutationQueue: Promise.resolve(),
        diagnostics: [] as ContentSystemViolation[],
        showDiagnostics: false,
        diagnoseRequestSequence: 0,
        latestDiagnoseRequestId: 0,
        editorStore,
        get canUndo() {
            return editorStore.canUndo;
        },
        $refs: { experienceStudioCanvas: { closeSettingsPanel: jest.fn() } },
        draftMutationService: () => ({ duplicateElement }),
        getLayoutRootSource: methods.getLayoutRootSource,
        resolveMutationRootSource: methods.resolveMutationRootSource,
        createDraftMutationPayload: methods.createDraftMutationPayload,
        requestDraftMutation: methods.requestDraftMutation,
        executeStructuralDraftMutation: methods.executeStructuralDraftMutation,
        syncActiveLayoutState: methods.syncActiveLayoutState,
        activateLayout: methods.activateLayout,
        onUndo: methods.onUndo,
        scheduleDiagnoseLayout: jest.fn(),
        diagnoseLayout: jest.fn(),
        notifyMutationError: jest.fn(),
        extractMutationErrorCodes: jest.fn().mockReturnValue([]),
    };

    return { editor, layouts, editorStore };
}

describe('structural mutations keep their originating layout', () => {
    beforeEach(() => {
        Shopware.Store.get('experienceStudioEditor').reset();
    });

    it('applies a background response and undo history to its origin without changing the active selection or diagnostics', async () => {
        const pending = deferred<ContentLayoutDraftMutationResponse>();
        const duplicateElement = jest.fn().mockReturnValue(pending.promise);
        const { editor, layouts, editorStore } = createEditor(duplicateElement);
        const originalA = Shopware.Utils.object.cloneDeep(layouts[0].layout);
        const originalB = Shopware.Utils.object.cloneDeep(layouts[1].layout);
        const mutation = methods.onDuplicateElement.call(editor, 'a');
        await flushPromises();
        editor.activateLayout('layout-b');
        editor.selectedElementId = 'b';
        editor.showDiagnostics = true;
        const activeDiagnostics = editor.diagnostics;
        const diagnosticRequestId = editor.latestDiagnoseRequestId;
        const updatedA = [...originalA, { id: 'a-copy', component: 'Sw:Content:Text' }];

        pending.resolve(response(updatedA, 'a-copy'));
        await mutation;
        await editor.mutationQueue;

        expect(layouts[0].layout).toEqual(updatedA);
        expect(layouts[1].layout).toEqual(originalB);
        expect(editor.activeLayoutId).toBe('layout-b');
        expect(editor.selectedElementId).toBe('b');
        expect(editor.openLayouts[0].selectedElementId).toBeNull();
        expect(editor.diagnostics).toBe(activeDiagnostics);
        expect(editor.showDiagnostics).toBe(true);
        expect(editor.latestDiagnoseRequestId).toBe(diagnosticRequestId);
        expect(editorStore.activeLayoutId).toBe('layout-b');
        expect(editorStore.canUndoLayout('layout-a')).toBe(true);
        expect(editorStore.canUndoLayout('layout-b')).toBe(false);
        expect(editor.pendingMutationCount).toBe(0);
        expect(editor.isLoading).toBe(false);

        methods.onFrameUndo.call(editor, 'layout-a');
        expect(layouts[0].layout).toEqual(originalA);
        expect(layouts[1].layout).toEqual(originalB);
    });

    it('keeps queued operations on their originating tree and root source after switching layouts', async () => {
        const pending = deferred<ContentLayoutDraftMutationResponse>();
        let sequence = 1;
        const duplicateElement = jest
            .fn<Promise<ContentLayoutDraftMutationResponse>, [ContentLayoutDraftDuplicatePayload]>()
            .mockImplementationOnce(() => pending.promise)
            .mockImplementation((payload: ContentLayoutDraftDuplicatePayload) => {
                const copyId = `copy-${++sequence}`;
                return Promise.resolve(response([...payload.layout, { id: copyId, component: 'Sw:Content:Text' }], copyId));
            });
        const { editor, layouts, editorStore } = createEditor(duplicateElement);
        const first = methods.onDuplicateElement.call(editor, 'a');
        const second = methods.onDuplicateElement.call(editor, 'a');
        editor.activateLayout('layout-b');
        const third = methods.onDuplicateElement.call(editor, 'b');
        await flushPromises();
        expect(duplicateElement).toHaveBeenCalledTimes(1);

        pending.resolve(response([...layouts[0].layout, { id: 'copy-1', component: 'Sw:Content:Text' }], 'copy-1'));
        await Promise.all([first, second, third]);
        await editor.mutationQueue;

        expect(
            duplicateElement.mock.calls.map(([payload]: [ContentLayoutDraftDuplicatePayload]) => payload.rootSource),
        ).toEqual([
            'product',
            'product',
            'category',
        ]);
        expect(duplicateElement).toHaveBeenNthCalledWith(
            2,
            expect.objectContaining({
                layout: [
                    { id: 'a', component: 'Sw:Content:Text' },
                    { id: 'copy-1', component: 'Sw:Content:Text' },
                ],
            }),
        );
        expect(layouts[0].layout.map((element) => element.id)).toEqual(['a', 'copy-1', 'copy-2']);
        expect(layouts[1].layout.map((element) => element.id)).toEqual(['b', 'copy-3']);
        expect(editorStore.historyByLayoutId['layout-a'].past).toHaveLength(2);
        expect(editorStore.historyByLayoutId['layout-b'].past).toHaveLength(1);
        expect(editorStore.activeLayoutId).toBe('layout-b');
    });

    it('ignores a late response after its originating layout is closed and reopened', async () => {
        const pending = deferred<ContentLayoutDraftMutationResponse>();
        const { editor, layouts, editorStore } = createEditor(jest.fn().mockReturnValue(pending.promise));
        const mutation = methods.onDuplicateElement.call(editor, 'a');
        await flushPromises();
        editor.activateLayout('layout-b');
        const reopened = { ...layouts[0], layout: [{ id: 'reopened-a', component: 'Sw:Content:Text' }] };
        editor.openLayouts[0] = { ...editor.openLayouts[0], layout: reopened };
        editorStore.removeLayout('layout-a');
        editorStore.initialize('layout-a');
        editorStore.initialize('layout-b');

        pending.resolve(response([{ id: 'stale-copy', component: 'Sw:Content:Text' }], 'stale-copy'));
        await mutation;
        await editor.mutationQueue;

        expect(reopened.layout).toEqual([{ id: 'reopened-a', component: 'Sw:Content:Text' }]);
        expect(layouts[1].layout).toEqual([{ id: 'b', component: 'Sw:Content:Text' }]);
        expect(editorStore.canUndoLayout('layout-a')).toBe(false);
        expect(editorStore.canUndoLayout('layout-b')).toBe(false);
        expect(editor.activeLayoutId).toBe('layout-b');
        expect(editor.pendingMutationCount).toBe(0);
    });

    it('keeps both layouts and histories unchanged when a background request fails', async () => {
        const pending = deferred<ContentLayoutDraftMutationResponse>();
        const { editor, layouts, editorStore } = createEditor(jest.fn().mockReturnValue(pending.promise));
        const original = Shopware.Utils.object.cloneDeep(layouts);
        const mutation = methods.onDuplicateElement.call(editor, 'a');
        await flushPromises();
        editor.activateLayout('layout-b');
        editor.selectedElementId = 'b';

        pending.reject(new Error('Request failed'));
        await mutation;
        await editor.mutationQueue;

        expect(layouts).toEqual(original);
        expect(editor.selectedElementId).toBe('b');
        expect(editorStore.canUndoLayout('layout-a')).toBe(false);
        expect(editorStore.canUndoLayout('layout-b')).toBe(false);
        expect(editor.notifyMutationError).toHaveBeenCalledTimes(1);
        expect(editor.isLoading).toBe(false);
    });
});
