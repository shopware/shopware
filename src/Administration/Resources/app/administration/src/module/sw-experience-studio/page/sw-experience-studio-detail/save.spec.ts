import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentSystemViolation } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import Entity from 'src/core/data/entity.data';
import { createContentLayoutRepository } from '../../util/content-layout-repository.util';
import detailComponent from './index';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
const computed = (detailComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;
const { cloneDeep } = Shopware.Utils.object;
const missingMedia: ContentSystemViolation = {
    code: 'missing_required_property',
    scope: 'intrinsic',
    severity: 'error',
    elementId: 'image',
    key: 'media',
    message: 'Required property "media" has no value.',
    candidates: [],
};

function createEditor(elements: ContentElementNode[] = []) {
    const repository = createContentLayoutRepository(Shopware.Service('repositoryFactory').create('content_layout'));
    const layout = Object.assign(repository.create(Shopware.Context.api), {
        id: 'layout-1',
        name: 'Layout',
        rootSource: 'category',
        layout: elements,
    });
    const diagnose = jest.fn().mockResolvedValue({ diagnostics: { violations: [] } });
    return {
        layout,
        activeLayoutId: layout.id,
        allowSave: true,
        selectedElementId: elements[0]?.id ?? null,
        diagnostics: [] as ContentSystemViolation[],
        showDiagnostics: false,
        diagnoseTimeoutId: null as number | null,
        diagnoseRequestSequence: 0,
        latestDiagnoseRequestId: 0,
        openLayouts: [{ id: layout.id, layout, savedState: '', isNew: true, isSaving: false, saveError: null }],
        layoutLoadCriteria: {},
        layoutRepository: {
            save: jest.fn().mockResolvedValue(undefined),
            get: jest.fn().mockResolvedValue(layout),
        },
        serializeLayoutState: methods.serializeLayoutState,
        getLayoutRootSource: (value: typeof layout) => value.rootSource,
        extractApiErrorDetail: methods.extractApiErrorDetail,
        reloadSavedLayout: methods.reloadSavedLayout,
        diagnoseLayout: jest.fn(methods.diagnoseLayout),
        draftMutationService: () => ({ diagnose }),
        createNotificationError: jest.fn(),
        $t: (key: string) => key,
    };
}

describe('workspace layout save errors and reload', () => {
    it('keeps the API error detail on the layout when saving is refused', async () => {
        const editor = createEditor([{ id: 'image', component: 'Sw:Media:Image', properties: {} }]);
        const authored = methods.serializeLayoutState.call(editor, editor.layout);
        editor.layoutRepository.save.mockRejectedValue({
            response: { data: { errors: [{ detail: 'Required property "media" has no value.' }] } },
        });
        editor.draftMutationService().diagnose.mockResolvedValue({ diagnostics: { violations: [missingMedia] } });

        expect(await methods.saveOpenLayout.call(editor, 'layout-1')).toBe(false);

        expect(editor.openLayouts[0].saveError).toBe('Required property "media" has no value.');
        expect(editor.openLayouts[0].isSaving).toBe(false);
        expect(editor.openLayouts[0].isNew).toBe(true);
        expect(editor.layout.isNew()).toBe(true);
        expect(editor.layoutRepository.get).not.toHaveBeenCalled();
        expect(methods.serializeLayoutState.call(editor, editor.layout)).toBe(authored);
        expect(editor.draftMutationService().diagnose).toHaveBeenCalledWith({
            layout: [{ id: 'image', component: 'Sw:Media:Image', properties: {} }],
            rootSource: 'category',
        });
        expect(editor.showDiagnostics).toBe(true);
        expect(computed.selectedElementViolations.call(editor)).toEqual([missingMedia]);
    });

    it('uses the generic error when the rejection carries no API detail', async () => {
        const editor = createEditor();
        editor.layoutRepository.save.mockRejectedValue(new Error('Network down'));

        expect(await methods.saveOpenLayout.call(editor, 'layout-1')).toBe(false);

        expect(editor.openLayouts[0].saveError).toBe('sw-experience-studio.detail.messageSaveError');
        expect(editor.openLayouts[0].isSaving).toBe(false);
    });

    it('clears the failed-save field highlights after a successful retry', async () => {
        const editor = createEditor([{ id: 'image', component: 'Sw:Media:Image', properties: {} }]);
        editor.layoutRepository.save.mockRejectedValueOnce(new Error('Save refused'));
        editor.draftMutationService().diagnose.mockResolvedValue({ diagnostics: { violations: [missingMedia] } });
        expect(await methods.saveOpenLayout.call(editor, editor.layout.id)).toBe(false);
        expect(computed.selectedElementViolations.call(editor)).toEqual([missingMedia]);

        expect(await methods.saveOpenLayout.call(editor, editor.layout.id)).toBe(true);

        expect(editor.showDiagnostics).toBe(false);
        expect(computed.selectedElementViolations.call(editor)).toEqual([]);
        expect(editor.openLayouts[0].saveError).toBeNull();
        expect(editor.draftMutationService().diagnose).toHaveBeenCalledTimes(1);
    });

    it.each([false, true])(
        'keeps the active layout diagnostics visible=%s when another layout fails to save',
        async (visible) => {
            const editor = createEditor([{ id: 'image', component: 'Sw:Media:Image', properties: {} }]);
            editor.layoutRepository.save.mockRejectedValue(new Error('Save refused'));
            editor.draftMutationService().diagnose.mockResolvedValue({ diagnostics: { violations: [missingMedia] } });
            const saving = methods.saveOpenLayout.call(editor, editor.layout.id);
            const active = createEditor().layout;
            Object.assign(active, { id: 'other-layout', rootSource: 'product' });
            editor.layout = active;
            editor.activeLayoutId = active.id;
            const activeDiagnostics = [{ ...missingMedia, elementId: 'other-image' }];
            editor.diagnostics = activeDiagnostics;
            editor.showDiagnostics = visible;
            editor.diagnoseRequestSequence = 7;
            editor.latestDiagnoseRequestId = 7;

            expect(await saving).toBe(false);

            expect(editor.draftMutationService().diagnose).toHaveBeenCalledWith({
                layout: [{ id: 'image', component: 'Sw:Media:Image', properties: {} }],
                rootSource: 'category',
            });
            expect(editor.layout).toBe(active);
            expect(editor.diagnostics).toBe(activeDiagnostics);
            expect(editor.showDiagnostics).toBe(visible);
            expect(editor.latestDiagnoseRequestId).toBe(7);
            expect(editor.openLayouts[0].saveError).toBe('sw-experience-studio.detail.messageSaveError');
        },
    );

    it('does not apply a failed-save diagnosis after the active layout changes', async () => {
        const editor = createEditor([{ id: 'image', component: 'Sw:Media:Image', properties: {} }]);
        editor.layoutRepository.save.mockRejectedValue(new Error('Save refused'));
        let finishDiagnosis!: () => void;
        editor.draftMutationService().diagnose.mockImplementation(
            () =>
                new Promise((resolve) => {
                    finishDiagnosis = () => resolve({ diagnostics: { violations: [missingMedia] } });
                }),
        );
        const saving = methods.saveOpenLayout.call(editor, editor.layout.id);
        await flushPromises();
        expect(editor.draftMutationService().diagnose).toHaveBeenCalledTimes(1);
        const active = createEditor().layout;
        Object.assign(active, { id: 'other-layout' });
        editor.layout = active;
        editor.activeLayoutId = active.id;
        const activeDiagnostics = [{ ...missingMedia, elementId: 'other-image' }];
        editor.diagnostics = activeDiagnostics;
        finishDiagnosis();

        expect(await saving).toBe(false);
        expect(editor.diagnostics).toBe(activeDiagnostics);
        expect(editor.showDiagnostics).toBe(false);
        expect(editor.createNotificationError).not.toHaveBeenCalled();
    });

    it('retains the saved snapshot when the subsequent reload fails', async () => {
        const editor = createEditor();
        editor.layoutRepository.get.mockRejectedValue(new Error('Network down'));

        expect(await methods.saveOpenLayout.call(editor, 'layout-1')).toBe(true);

        expect(editor.layoutRepository.save).toHaveBeenCalledTimes(1);
        expect(editor.openLayouts[0].isNew).toBe(false);
        expect(editor.openLayouts[0].savedState).toBe(
            JSON.stringify({ name: 'Layout', rootSource: 'category', layout: [] }),
        );
        expect(editor.openLayouts[0].isSaving).toBe(false);
        expect(editor.openLayouts[0].saveError).toBeNull();
        expect(editor.layout.isNew()).toBe(false);
        expect(editor.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-experience-studio.detail.messageReloadError',
        });
    });

    it('adopts the server-canonical layout after saving without client-side normalization', async () => {
        const authoredElement: ContentElementNode = {
            component: 'Sw:Filter:Panel',
            style: { 'col-span': 6 },
            id: 'element-1',
            properties: { visibleFilterCount: 5 },
        };
        const canonicalElement: ContentElementNode = {
            id: 'element-1',
            component: 'Sw:Filter:Panel',
            properties: { visibleFilterCount: 5, showLayoutSwitch: true },
            style: { 'col-span': { xs: 6, sm: 6, md: 6, lg: 6, xl: 6, xxl: 6 } },
        };
        const editor = createEditor([authoredElement]);
        const reloaded = { ...editor.layout, layout: [canonicalElement] };
        editor.layoutRepository.get.mockResolvedValue(reloaded);

        expect(await methods.saveOpenLayout.call(editor, 'layout-1')).toBe(true);

        expect(editor.layoutRepository.save).toHaveBeenCalledWith(
            expect.objectContaining({ layout: [authoredElement] }),
            Shopware.Context.api,
        );
        expect(editor.layout).toBe(reloaded);
        expect(editor.openLayouts[0].layout).toBe(reloaded);
        expect(editor.layout.layout[0]).toBe(canonicalElement);
        expect(editor.openLayouts[0].savedState).toBe(
            JSON.stringify({ name: reloaded.name, rootSource: reloaded.rootSource, layout: [canonicalElement] }),
        );
    });

    it('keeps a pending rename and canonical elements on the originating layout after switching layouts', async () => {
        const editor = createEditor([{ id: 'text', component: 'Sw:Content:Text', properties: {} }]);
        const openLayout = editor.openLayouts[0];
        const canonicalElements = [{ id: 'text', component: 'Sw:Content:Text', properties: { text: 'Default' } }];
        const reloaded = { ...editor.layout, layout: canonicalElements };
        let finishReload!: () => void;
        editor.layoutRepository.get.mockImplementation(
            () =>
                new Promise((resolve) => {
                    finishReload = () => resolve(reloaded);
                }),
        );

        const saving = methods.saveOpenLayout.call(editor, openLayout.id);
        await flushPromises();
        editor.layout.name = 'Pending name';
        const otherLayout = createEditor().layout;
        Object.assign(otherLayout, { id: 'layout-2' });
        editor.activeLayoutId = otherLayout.id;
        editor.layout = otherLayout;
        finishReload();

        expect(await saving).toBe(true);
        expect(editor.layout).toBe(otherLayout);
        expect(openLayout.layout.name).toBe('Pending name');
        expect(openLayout.layout.layout).toBe(canonicalElements);
        expect(JSON.parse(openLayout.savedState)).toEqual({
            name: 'Layout',
            rootSource: 'category',
            layout: canonicalElements,
        });
        expect(methods.isLayoutDirty.call(editor, openLayout)).toBe(true);
    });
});

describe('saving a new layout with the entity repository', () => {
    afterEach(() => {
        jest.restoreAllMocks();
    });

    it.each(['write', 'reload'])('keeps edits made during the first %s and sends PATCH on the next save', async (phase) => {
        const editor = createEditor([{ id: 'text', component: 'Sw:Content:Text', properties: { text: 'Original' } }]);
        const original = editor.layout;
        const repository = Shopware.Service('repositoryFactory').create('content_layout');
        const submitted = {
            ...original.getOrigin(),
            id: original.id,
            name: original.name,
            rootSource: original.rootSource,
            layout: cloneDeep(original.layout),
        };
        const reloaded = new Entity(original.id, 'content_layout', submitted);
        let finishWrite!: () => void;
        const post = jest.spyOn(repository.httpClient, 'post').mockImplementation(
            () =>
                new Promise((resolve) => {
                    finishWrite = () => resolve({ data: {}, status: 204, statusText: '', headers: {}, config: {} });
                }),
        );
        const patch = jest.spyOn(repository.httpClient, 'patch').mockResolvedValue({ data: {}, status: 204 });
        let finishReload!: () => void;
        const get = jest.spyOn(repository, 'get').mockImplementation(
            () =>
                new Promise((resolve) => {
                    finishReload = () => resolve(reloaded);
                }),
        );
        Object.assign(editor, { layoutRepository: createContentLayoutRepository(repository) });

        const saving = methods.saveOpenLayout.call(editor, original.id);
        await flushPromises();
        if (phase === 'reload') {
            finishWrite();
            await flushPromises();
        }
        editor.layout.name = 'Edited name';
        editor.layout.layout[0].properties = { text: 'Edited while saving' };
        if (phase === 'write') {
            finishWrite();
            await flushPromises();
        }
        finishReload();

        expect(await saving).toBe(true);
        expect(editor.layout.isNew()).toBe(false);
        expect(editor.layout.name).toBe('Edited name');
        expect(editor.layout.layout[0].properties).toEqual({ text: 'Edited while saving' });
        expect(methods.isLayoutDirty.call(editor, editor.openLayouts[0])).toBe(true);
        expect(JSON.parse(editor.openLayouts[0].savedState)).toMatchObject({ name: 'Layout' });

        get.mockResolvedValueOnce(
            new Entity(original.id, 'content_layout', {
                ...submitted,
                name: editor.layout.name,
                layout: cloneDeep(editor.layout.layout),
            }),
        );
        expect(await methods.saveOpenLayout.call(editor, original.id)).toBe(true);
        expect(post).toHaveBeenCalledTimes(1);
        expect(patch).toHaveBeenCalledTimes(1);
        expect(patch).toHaveBeenCalledWith(
            `/content-layout/${original.id}`,
            expect.objectContaining({ name: 'Edited name', layout: editor.layout.layout }),
            expect.any(Object),
        );
        expect(methods.isLayoutDirty.call(editor, editor.openLayouts[0])).toBe(false);
    });

    it.each(['failed', 'missing'])('allows PATCH after a successful create with a %s reload', async (reload) => {
        const editor = createEditor();
        const repository = Shopware.Service('repositoryFactory').create('content_layout');
        const origin = cloneDeep(editor.layout.getOrigin());
        const post = jest.spyOn(repository.httpClient, 'post').mockResolvedValue({ data: {}, status: 204 });
        const patch = jest.spyOn(repository.httpClient, 'patch').mockResolvedValue({ data: {}, status: 204 });
        const get = jest.spyOn(repository, 'get');
        if (reload === 'failed') {
            get.mockRejectedValueOnce(new Error('Network down'));
        } else {
            get.mockResolvedValueOnce(null);
        }
        Object.assign(editor, { layoutRepository: createContentLayoutRepository(repository) });

        const saving = methods.saveOpenLayout.call(editor, editor.layout.id);
        editor.layout.name = 'Edited during create';
        expect(await saving).toBe(true);
        expect(editor.openLayouts[0].saveError).toBeNull();
        expect(editor.layout.isNew()).toBe(false);
        expect(editor.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-experience-studio.detail.messageReloadError',
        });
        expect(methods.isLayoutDirty.call(editor, editor.openLayouts[0])).toBe(true);

        get.mockResolvedValueOnce(
            new Entity(editor.layout.id, 'content_layout', {
                ...origin,
                name: editor.layout.name,
                rootSource: editor.layout.rootSource,
                layout: [],
            }),
        );
        expect(await methods.saveOpenLayout.call(editor, editor.layout.id)).toBe(true);
        expect(post).toHaveBeenCalledTimes(1);
        expect(patch).toHaveBeenCalledWith(
            `/content-layout/${editor.layout.id}`,
            { id: editor.layout.id, name: 'Edited during create' },
            expect.any(Object),
        );
        expect(methods.isLayoutDirty.call(editor, editor.openLayouts[0])).toBe(false);
    });
});
