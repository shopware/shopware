import type { ContentElementNode } from 'src/core/service/content-element.types';
import type {
    ContentLayoutDraftMutationResponse,
    ContentSystemViolation,
} from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import { createContentLayoutRepository } from '../../util/content-layout-repository.util';
import detailComponent from './index';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
const { cloneDeep } = Shopware.Utils.object;

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

function createEditor(elements: ContentElementNode[]) {
    const repository = createContentLayoutRepository(Shopware.Service('repositoryFactory').create('content_layout'));
    const layout = Object.assign(repository.create(Shopware.Context.api), {
        name: 'Edited layout',
        rootSource: 'category',
        layout: elements,
    });
    const write = deferred<void>();
    const reloaded = { ...layout, layout: cloneDeep(elements) };
    const editor = {
        layout,
        activeLayoutId: layout.id,
        allowSave: true,
        isSavingAll: false,
        openLayouts: [{ id: layout.id, layout, savedState: '', isNew: true, isSaving: false, saveError: null }],
        layoutRepository: { save: jest.fn().mockReturnValue(write.promise), get: jest.fn().mockResolvedValue(reloaded) },
        layoutLoadCriteria: {},
        pendingMutationCount: 0,
        mutationQueue: Promise.resolve(),
        isLoading: false,
        selectedElementId: elements[0]?.id ?? null,
        showDiagnostics: true,
        diagnostics: [] as ContentSystemViolation[],
        diagnoseRequestSequence: 0,
        latestDiagnoseRequestId: 0,
        editorStore: { pushToHistory: jest.fn() },
        requestDraftMutation: jest.fn(),
        notifyMutationError: jest.fn(),
        extractMutationErrorCodes: jest.fn().mockReturnValue([]),
        resolveMutationRootSource: () => 'category',
        getLayoutRootSource: methods.getLayoutRootSource,
        serializeLayoutState: methods.serializeLayoutState,
        isLayoutDirty: methods.isLayoutDirty,
        saveOpenLayout: methods.saveOpenLayout,
        reloadSavedLayout: methods.reloadSavedLayout,
        extractApiErrorDetail: methods.extractApiErrorDetail,
        syncActiveLayoutState: jest.fn(),
        createNotificationSuccess: jest.fn(),
        createNotificationError: jest.fn(),
        $t: (key: string) => key,
    };
    return { editor, write, reloaded };
}

describe('saving while structural draft mutations are pending', () => {
    it('retains a deletion whose response arrives after the save reload', async () => {
        const elements = [{ id: 'text', component: 'Sw:Content:Text' }];
        const { editor, write, reloaded } = createEditor(elements);
        const original = editor.layout;
        const removal = deferred<ContentLayoutDraftMutationResponse>();
        editor.requestDraftMutation.mockReturnValueOnce(removal.promise);

        const saving = methods.saveAllOpenLayouts.call(editor);
        const deleting = methods.executeStructuralDraftMutation.call(editor, 'remove', { elementId: 'text' }, () => null);
        await flushPromises();
        write.resolve();
        await flushPromises();

        expect(editor.layoutRepository.get).toHaveBeenCalledTimes(1);
        expect(editor.layout).toBe(original);
        expect(editor.openLayouts[0].isSaving).toBe(true);

        removal.resolve(response([], 'text'));
        await deleting;
        expect(await saving).toBe(true);

        expect(editor.layout).toBe(reloaded);
        expect(editor.layout.layout).toEqual([]);
        expect(editor.openLayouts[0].layout).toBe(reloaded);
        expect(methods.isLayoutDirty.call(editor, editor.openLayouts[0])).toBe(true);
        expect(JSON.parse(editor.openLayouts[0].savedState)).toMatchObject({ layout: elements });
        expect(editor.selectedElementId).toBeNull();
        expect(editor.showDiagnostics).toBe(false);
        expect(editor.editorStore.pushToHistory).toHaveBeenCalledWith(elements, 'text', original.id);
        expect(editor.pendingMutationCount).toBe(0);
        expect(editor.isLoading).toBe(false);
        expect(editor.notifyMutationError).not.toHaveBeenCalled();
    });

    it.each(['before reload', 'while waiting'])(
        'retains queued mutations added %s before adopting the saved entity',
        async (queuedAt) => {
            const elements = [
                { id: 'text', component: 'Sw:Content:Text' },
                { id: 'remaining', component: 'Sw:Content:Text' },
            ];
            const { editor, write, reloaded } = createEditor(elements);
            const original = editor.layout;
            const removal = deferred<ContentLayoutDraftMutationResponse>();
            const duplication = deferred<ContentLayoutDraftMutationResponse>();
            editor.requestDraftMutation.mockReturnValueOnce(removal.promise).mockReturnValueOnce(duplication.promise);
            const duplicate = () =>
                methods.executeStructuralDraftMutation.call(editor, 'duplicate', { elementId: 'remaining' }, () => 'copy');

            const saving = methods.saveAllOpenLayouts.call(editor);
            const deleting = methods.executeStructuralDraftMutation.call(
                editor,
                'remove',
                { elementId: 'text' },
                () => null,
            );
            const duplicating = queuedAt === 'before reload' ? duplicate() : null;
            await flushPromises();
            write.resolve();
            await flushPromises();
            const lateDuplicating = queuedAt === 'while waiting' ? duplicate() : null;

            removal.resolve(response([elements[1]], 'text'));
            await deleting;
            await flushPromises();

            expect(editor.requestDraftMutation).toHaveBeenNthCalledWith(
                2,
                'duplicate',
                [elements[1]],
                { elementId: 'remaining' },
                'category',
            );
            expect(editor.layout).toBe(original);
            expect(editor.openLayouts[0].isSaving).toBe(true);
            const finalElements = [elements[1], { id: 'copy', component: 'Sw:Content:Text' }];
            duplication.resolve(response(finalElements, 'copy'));
            await Promise.all([duplicating, lateDuplicating]);
            expect(await saving).toBe(true);

            expect(editor.layout).toBe(reloaded);
            expect(editor.layout.layout).toEqual(finalElements);
            expect(editor.selectedElementId).toBe('copy');
            expect(editor.editorStore.pushToHistory).toHaveBeenCalledTimes(2);
            expect(methods.isLayoutDirty.call(editor, editor.openLayouts[0])).toBe(true);
            expect(editor.pendingMutationCount).toBe(0);
            expect(editor.notifyMutationError).not.toHaveBeenCalled();
        },
    );

    it('finishes saving and reports the mutation error when a pending operation fails', async () => {
        const elements = [{ id: 'text', component: 'Sw:Content:Text' }];
        const { editor, write, reloaded } = createEditor(elements);
        const removal = deferred<ContentLayoutDraftMutationResponse>();
        editor.requestDraftMutation.mockReturnValueOnce(removal.promise);
        editor.extractMutationErrorCodes.mockReturnValue(['CONTENT_SYSTEM__MUTATION_TARGET_NOT_FOUND']);

        const saving = methods.saveAllOpenLayouts.call(editor);
        const deleting = methods.executeStructuralDraftMutation.call(editor, 'remove', { elementId: 'text' }, () => null);
        await flushPromises();
        write.resolve();
        await flushPromises();
        removal.reject(new Error('Mutation refused'));
        await deleting;
        expect(await saving).toBe(true);

        expect(editor.layout).toBe(reloaded);
        expect(editor.layout.layout).toEqual(elements);
        expect(editor.notifyMutationError).toHaveBeenCalledWith(['CONTENT_SYSTEM__MUTATION_TARGET_NOT_FOUND']);
        expect(editor.editorStore.pushToHistory).not.toHaveBeenCalled();
        expect(editor.openLayouts[0].isSaving).toBe(false);
        expect(editor.pendingMutationCount).toBe(0);
        expect(editor.isLoading).toBe(false);
        expect(editor.createNotificationError).not.toHaveBeenCalled();
    });
});
