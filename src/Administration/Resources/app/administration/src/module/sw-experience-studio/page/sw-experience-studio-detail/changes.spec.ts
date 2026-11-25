import detailComponent from './index';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
const computed = (detailComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;

function createLayout(id: string, isNew = false) {
    const layout = {
        id,
        name: id,
        rootSource: 'category',
        layout: [{ id: `${id}-text`, component: 'Sw:Content:Text', properties: { text: 'Saved content' } }],
    };
    return {
        id,
        layout,
        savedState: JSON.stringify({ name: layout.name, rootSource: layout.rootSource, layout: layout.layout }),
        isNew,
        isSaving: false,
        saveError: null as string | null,
        selectedElementId: null as string | null,
        viewport: 'desktop',
        previewEntityId: null as string | null,
    };
}

function createVm() {
    const active = createLayout('active');
    const inactive = createLayout('inactive');
    active.layout.name = 'Changed active';
    inactive.layout.layout[0].properties.text = 'Changed inactive';
    return {
        openLayouts: [active, inactive],
        layout: active.layout,
        activeLayoutId: active.id,
        selectedElementId: 'active-text' as string | null,
        allowSave: true,
        isLoading: false,
        isSavingAll: false,
        isWorkspaceTransitioning: false,
        pendingMutationCount: 0,
        showDiagnostics: true,
        diagnostics: [{ code: 'old' }],
        editorStore: { removeLayout: jest.fn(), initialize: jest.fn() },
        removeOpenLayout: jest.fn(),
        diagnoseLayout: jest.fn(),
        serializeLayoutState: methods.serializeLayoutState,
        getLayoutRootSource: (layout: { rootSource: string }) => layout.rootSource,
        isLayoutDirty: methods.isLayoutDirty,
        $refs: { experienceStudioCanvas: { closeSettingsPanel: jest.fn() } },
    };
}

describe('layout change review state', () => {
    it('counts changed active and inactive layouts and new layouts, excluding clean layouts and preview changes', () => {
        const vm = createVm();
        const clean = createLayout('clean');
        clean.selectedElementId = 'clean-text';
        clean.viewport = 'mobile';
        clean.previewEntityId = 'preview-id';
        vm.openLayouts.push(clean, createLayout('new', true));

        expect(computed.changedLayouts.call(vm)).toEqual([
            { id: 'active', name: 'Changed active' },
            { id: 'inactive', name: 'inactive' },
            { id: 'new', name: 'new' },
        ]);
    });

    it('restores only the inactive layout to its saved snapshot and clears its history and error', () => {
        const vm = createVm();
        const inactive = vm.openLayouts[1];
        inactive.saveError = 'Save failed';
        inactive.selectedElementId = 'inactive-text';

        methods.onDiscardLayout.call(vm, 'inactive');

        expect(inactive.layout.layout[0].properties.text).toBe('Saved content');
        expect(inactive.saveError).toBeNull();
        expect(inactive.selectedElementId).toBeNull();
        expect(methods.isLayoutDirty.call(vm, inactive)).toBe(false);
        expect(vm.layout.name).toBe('Changed active');
        expect(vm.selectedElementId).toBe('active-text');
        expect(vm.editorStore.removeLayout).toHaveBeenCalledWith('inactive');
        expect(vm.$refs.experienceStudioCanvas.closeSettingsPanel).not.toHaveBeenCalled();
        expect(vm.diagnoseLayout).not.toHaveBeenCalled();
        expect(computed.changedLayouts.call(vm)).toEqual([{ id: 'active', name: 'Changed active' }]);
    });

    it('restores the active layout and resets settings, diagnostics, and undo history', () => {
        const vm = createVm();
        const active = vm.openLayouts[0];
        active.layout.rootSource = 'product';

        methods.onDiscardLayout.call(vm, 'active');

        expect(vm.layout.name).toBe('active');
        expect(vm.layout.rootSource).toBe('category');
        expect(methods.isLayoutDirty.call(vm, active)).toBe(false);
        expect(vm.selectedElementId).toBeNull();
        expect(vm.showDiagnostics).toBe(false);
        expect(vm.diagnostics).toEqual([]);
        expect(vm.editorStore.removeLayout).toHaveBeenCalledWith('active');
        expect(vm.editorStore.initialize).toHaveBeenCalledWith('active');
        expect(vm.$refs.experienceStudioCanvas.closeSettingsPanel).toHaveBeenCalledTimes(1);
        expect(vm.diagnoseLayout).toHaveBeenCalledTimes(1);
        expect(vm.openLayouts[1].layout.layout[0].properties.text).toBe('Changed inactive');
    });

    it.each(['active', 'inactive'])('removes a new unsaved %s layout instead of restoring an unsaved snapshot', (id) => {
        const vm = createVm();
        vm.openLayouts[id === 'active' ? 0 : 1].isNew = true;

        methods.onDiscardLayout.call(vm, id);

        expect(vm.removeOpenLayout).toHaveBeenCalledWith(id);
        expect(vm.$refs.experienceStudioCanvas.closeSettingsPanel).toHaveBeenCalledTimes(id === 'active' ? 1 : 0);
        expect(vm.editorStore.removeLayout).not.toHaveBeenCalled();
        expect(vm.diagnoseLayout).not.toHaveBeenCalled();
    });

    it.each([
        { allowSave: false },
        { isLoading: true },
        { isSavingAll: true },
        { isWorkspaceTransitioning: true },
        { pendingMutationCount: 1 },
    ])('does not discard when editing is unavailable or busy: %j', (state) => {
        const vm = { ...createVm(), ...state };
        const before = JSON.stringify(vm.openLayouts);

        methods.onDiscardLayout.call(vm, 'active');

        expect(JSON.stringify(vm.openLayouts)).toBe(before);
        expect(vm.editorStore.removeLayout).not.toHaveBeenCalled();
        expect(vm.$refs.experienceStudioCanvas.closeSettingsPanel).not.toHaveBeenCalled();
    });

    it('does not discard while any layout is being saved', () => {
        const vm = createVm();
        vm.openLayouts[1].isSaving = true;
        methods.onDiscardLayout.call(vm, 'active');

        expect(vm.layout.name).toBe('Changed active');
        expect(vm.editorStore.removeLayout).not.toHaveBeenCalled();
    });

    it('ignores missing and already clean layouts', () => {
        const vm = createVm();
        vm.openLayouts.push(createLayout('clean'));
        methods.onDiscardLayout.call(vm, 'missing');
        methods.onDiscardLayout.call(vm, 'clean');

        expect(vm.editorStore.removeLayout).not.toHaveBeenCalled();
        expect(vm.removeOpenLayout).not.toHaveBeenCalled();
    });
});
