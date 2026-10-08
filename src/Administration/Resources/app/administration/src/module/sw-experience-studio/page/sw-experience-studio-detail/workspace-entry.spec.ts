import type { RouteLocationNormalized } from 'vue-router';
import detailComponent from './index';

const component = detailComponent as unknown as {
    created: () => void;
    beforeRouteUpdate: (to: RouteLocationNormalized) => boolean;
    computed: Record<string, (...args: unknown[]) => unknown>;
    methods: Record<string, (...args: unknown[]) => unknown>;
};

function createEditor(params: Record<string, string> = {}) {
    return {
        $route: { params },
        workspaceStore: { activate: jest.fn(), activeWorkspace: null as { id: string } | null },
        openWorkspace: jest.fn(),
        loadElementTypes: jest.fn(),
        loadStyleOptions: jest.fn(),
        loadLayoutPresets: jest.fn(),
        loadLayoutTypes: jest.fn(),
        loadMappingCandidates: jest.fn(),
        onHistoryKeydown: jest.fn(),
        pendingLinkedLayoutId: null as string | null,
        isWorkspacePickerOpen: false,
    };
}

describe('Experience Studio workspace entry', () => {
    beforeEach(() => {
        jest.spyOn(Shopware.Store.get('adminMenu'), 'collapseSidebar').mockImplementation(() => {});
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('requires workspace selection when opening the studio', () => {
        const editor = createEditor();
        component.created.call(editor);

        expect(editor.workspaceStore.activate).toHaveBeenCalledWith(null);
        expect(editor.isWorkspacePickerOpen).toBe(true);
        expect(editor.openWorkspace).not.toHaveBeenCalled();
    });

    it('opens a workspace directly when its ID is in the route', () => {
        const editor = createEditor({ workspaceId: 'workspace-1' });
        component.created.call(editor);

        expect(editor.openWorkspace).toHaveBeenCalledWith('workspace-1');
        expect(editor.workspaceStore.activate).not.toHaveBeenCalled();
    });

    it('holds a linked layout until the user selects a workspace', () => {
        const editor = createEditor({ id: 'linked-layout' });
        component.created.call(editor);

        expect(editor.pendingLinkedLayoutId).toBe('linked-layout');
        expect(editor.isWorkspacePickerOpen).toBe(true);
        expect(component.computed.layoutId.call({ activeLayoutId: null, $route: editor.$route })).toBe('');
    });

    it('opens a linked layout inside the active workspace on navigation', () => {
        const editor = { workspaceStore: { activeWorkspace: { id: 'workspace' } }, openLayoutById: jest.fn() };
        const destination = {
            name: 'sw.experience.studio.detail',
            params: { id: 'linked-layout' },
        } as unknown as RouteLocationNormalized;

        expect(component.beforeRouteUpdate.call(editor, destination)).toBe(false);
        expect(editor.openLayoutById).toHaveBeenCalledWith('linked-layout');
    });

    it('does not create a standalone layout without a workspace', () => {
        const editor = { workspaceStore: { activeWorkspace: null }, layoutRepository: { create: jest.fn() } };
        component.methods.onCreateWizardComplete.call(editor, { name: 'New layout', type: 'category' });

        expect(editor.layoutRepository.create).not.toHaveBeenCalled();
    });

    it('creates and activates a new unsaved layout in the current workspace', async () => {
        const layout = { id: 'new-layout', name: '', rootSource: '', layout: [] };
        const editor = {
            workspaceStore: { activeWorkspace: { id: 'workspace' } },
            layoutRepository: { create: jest.fn().mockReturnValue(layout) },
            openLayouts: [] as Array<{ id: string; isNew: boolean }>,
            createOpenLayoutState: component.methods.createOpenLayoutState,
            serializeLayoutState: component.methods.serializeLayoutState,
            getLayoutRootSource: (item: typeof layout) => item.rootSource,
            isCreateLayoutModalOpen: true,
            activateLayout: jest.fn(),
            applyPreviewContextDefaults: jest.fn(),
            loadDefaultPreviewEntity: jest.fn(),
            editorStore: { initialize: jest.fn() },
            syncActiveLayoutState: jest.fn(),
            $nextTick: (callback: () => void) => Promise.resolve().then(callback),
            $refs: { experienceStudioCanvas: { fitCanvasToFrame: jest.fn() } },
        };
        component.methods.onCreateWizardComplete.call(editor, { name: 'New layout', type: 'category' });
        await flushPromises();

        expect(layout).toMatchObject({ name: 'New layout', rootSource: 'category', version: '1.0.0', layout: [] });
        expect(editor.openLayouts).toEqual([expect.objectContaining({ id: 'new-layout', layout, isNew: true })]);
        expect(editor.activateLayout).toHaveBeenCalledWith('new-layout');
        expect(editor.isCreateLayoutModalOpen).toBe(false);
        expect(editor.$refs.experienceStudioCanvas.fitCanvasToFrame).toHaveBeenCalledTimes(1);
    });
});
