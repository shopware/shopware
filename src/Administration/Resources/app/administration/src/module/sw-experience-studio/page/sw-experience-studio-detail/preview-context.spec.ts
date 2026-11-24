import { shallowMount } from '@vue/test-utils';
import type { Component, ComponentPublicInstance } from 'vue';
import detailComponent from './index';
import '../../store/experience-studio-workspace.store';

type PreviewEntitySelectProps = {
    entity: string | null;
    value: string | null;
    disabled: boolean;
};

type PreviewProps = {
    entityType: string | null;
};

async function createWrapper() {
    const [
        canvasComponent,
        treeComponent,
        treeNodeComponent,
        gridComponent,
    ] = await Promise.all([
        wrapTestComponent('sw-experience-studio-canvas', { sync: true }),
        wrapTestComponent('sw-experience-studio-sidebar-tree', { sync: true }),
        wrapTestComponent('sw-experience-studio-sidebar-tree-node', { sync: true }),
        wrapTestComponent('sw-data-grid', { sync: true }),
    ]);

    return shallowMount(
        {
            ...detailComponent,
            created() {},
            mounted() {},
            beforeUnmount() {},
            methods: {
                ...detailComponent.methods,
                diagnoseLayout: jest.fn(),
            },
        } as Component,
        {
            global: {
                provide: {
                    acl: { can: () => true },
                    repositoryFactory: { create: jest.fn() },
                },
                mocks: { $route: { name: 'sw.experience.studio.detail', params: { id: 'layout-a' } } },
                stubs: {
                    'sw-page': { template: '<div><slot name="content" /></div>' },
                    'sw-experience-studio-canvas': canvasComponent,
                    'sw-data-grid': gridComponent,
                    'sw-provide': { inheritAttrs: false, template: '<slot />' },
                    'sw-experience-studio-toolbar': true,
                    'sw-entity-single-select': {
                        name: 'test-preview-entity-select',
                        props: ['entity', 'value', 'disabled'],
                        template: '<div class="test-preview-entity-select" />',
                    },
                    'sw-experience-studio-sidebar-tree': treeComponent,
                    'sw-experience-studio-sidebar-tree-node': treeNodeComponent,
                    'sw-experience-studio-preview': {
                        name: 'test-preview',
                        props: ['layout', 'entityId', 'entityType'],
                        template: '<div class="test-preview" />',
                    },
                    'sw-experience-studio-element-settings': true,
                    'sw-experience-studio-element-picker': true,
                    'sw-experience-studio-layout-picker': true,
                    'sw-modal': true,
                    'sw-experience-studio-create-wizard': true,
                },
            },
        },
    );
}

describe('Experience Studio frame preview context', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    beforeEach(async () => {
        wrapper = await createWrapper();
        const stage = wrapper.get('.sw-experience-studio-canvas__stage').element;
        Object.defineProperties(stage, {
            clientWidth: { configurable: true, value: 1200 },
            clientHeight: { configurable: true, value: 800 },
        });
        jest.spyOn(stage, 'getBoundingClientRect').mockReturnValue({ width: 1200, height: 800 } as DOMRect);
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    async function openFrames(rootSources: string[]) {
        const layouts = rootSources.map((rootSource) => ({
            id: `layout-${rootSource}`,
            name: rootSource,
            rootSource,
            layout: [],
        }));
        await wrapper.setData({
            availableLayoutTypes: ['product'],
            activeLayoutId: null,
            layout: null,
            openLayouts: layouts.map((layout) => ({
                id: layout.id,
                layout,
                selectedElementId: null,
                viewport: 'desktop',
                previewEntityId: 'preview-entity',
                settingsWidth: 320,
                savedState: '',
                isNew: false,
                isSaving: false,
                saveError: null,
            })),
        });
        await flushPromises();

        return layouts;
    }

    async function showPreview(layoutId: string) {
        const canvas = wrapper.getComponent<ComponentPublicInstance>({ ref: 'experienceStudioCanvas' });
        await canvas.setData({
            frameStates: { [layoutId]: { x: 0, y: 0, width: 400, height: 300 } },
        });
        const workspace = canvas.get('.sw-experience-studio-canvas__stage').element;
        jest.spyOn(workspace, 'getBoundingClientRect').mockReturnValue({ width: 1200, height: 800 } as DOMRect);
        await canvas.setData({ canvasPanX: 1 });
        await flushPromises();
    }

    it.each([
        'header',
        'swag-sidebar',
    ])('gives a %s frame no preview entity type and a disabled picker without an entity', async (rootSource) => {
        const [layout] = await openFrames([rootSource]);
        await showPreview(layout.id);
        const frame = wrapper.get(`[data-frame-id="${layout.id}"]`);
        const picker = frame.getComponent<ComponentPublicInstance<PreviewEntitySelectProps>>({
            name: 'test-preview-entity-select',
        });
        const preview = frame.getComponent<ComponentPublicInstance<PreviewProps>>({ name: 'test-preview' });

        expect(picker.props('entity')).toBeNull();
        expect(picker.props('disabled')).toBe(true);
        expect(preview.props('entityType')).toBeNull();
    });

    it('hides saved preview entities after the entity type request fails', async () => {
        const layouts = await openFrames(['product', 'category']);
        await wrapper.setData({
            activeLayoutId: layouts[0].id,
            previewEntityId: 'preview-entity',
            availableLayoutTypes: [],
            layoutTypeLoadError: 'Failed to load layout types.',
        });
        await showPreview(layouts[0].id);

        const pickers = wrapper.findAllComponents<ComponentPublicInstance<PreviewEntitySelectProps>>({
            name: 'test-preview-entity-select',
        });

        expect(pickers).toHaveLength(2);
        expect(pickers.map((picker) => picker.props('value'))).toEqual([null, null]);
        expect(pickers.map((picker) => picker.props('entity'))).toEqual([null, null]);
        expect(pickers.map((picker) => picker.props('disabled'))).toEqual([true, true]);
    });

    // Regression guard: an entity root source keeps its preview entity type and an enabled picker.
    it('gives a product frame its entity type and an enabled picker', async () => {
        const [layout] = await openFrames(['product']);
        await showPreview(layout.id);
        const frame = wrapper.get(`[data-frame-id="${layout.id}"]`);
        const picker = frame.getComponent<ComponentPublicInstance<PreviewEntitySelectProps>>({
            name: 'test-preview-entity-select',
        });
        const preview = frame.getComponent<ComponentPublicInstance<PreviewProps>>({ name: 'test-preview' });

        expect(picker.props('entity')).toBe('product');
        expect(picker.props('disabled')).toBeFalsy();
        expect(preview.props('entityType')).toBe('product');
    });
});
