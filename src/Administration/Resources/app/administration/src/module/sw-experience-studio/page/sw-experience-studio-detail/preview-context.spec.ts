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
    entityId: string | null;
};

async function createWrapper() {
    const [
        canvasComponent,
        frameMenuComponent,
        treeComponent,
        treeNodeComponent,
        gridComponent,
    ] = await Promise.all([
        wrapTestComponent('sw-experience-studio-canvas', { sync: true }),
        wrapTestComponent('sw-experience-studio-frame-menu', { sync: true }),
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
                    'sw-experience-studio-frame-menu': frameMenuComponent,
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

    async function openFrames(rootSources: string[], activeRootSource: string) {
        const layouts = rootSources.map((rootSource) => ({
            id: `layout-${rootSource}`,
            name: rootSource,
            rootSource,
            layout: [],
        }));
        await wrapper.setData({
            availableLayoutTypes: ['product'],
            activeFrameId: `frame-${activeRootSource}`,
            previewEntityId: 'active-entity',
            layout: layouts.find((layout) => layout.rootSource === activeRootSource),
            frames: rootSources.map((rootSource) => ({
                id: `frame-${rootSource}`,
                layoutId: `layout-${rootSource}`,
                selectedElementId: null,
                viewport: 'desktop',
                previewEntityId: `stored-${rootSource}`,
            })),
            openLayouts: layouts.map((layout) => ({
                id: layout.id,
                layout,
                savedState: '',
                isNew: false,
                isSaving: false,
                saveError: null,
            })),
        });
        await flushPromises();
        await showPreviews(rootSources.map((rootSource) => `frame-${rootSource}`));
    }

    async function showPreviews(frameIds: string[]) {
        const canvas = wrapper.getComponent<ComponentPublicInstance>({ ref: 'experienceStudioCanvas' });
        await canvas.setData({
            frameStates: Object.fromEntries(frameIds.map((id) => [id, { x: 0, y: 0, width: 400, height: 300 }])),
        });
        const workspace = canvas.get('.sw-experience-studio-canvas__stage').element;
        jest.spyOn(workspace, 'getBoundingClientRect').mockReturnValue({ width: 1200, height: 800 } as DOMRect);
        await canvas.setData({ canvasPanX: 1 });
        await flushPromises();
    }

    function previewOf(frameId: string) {
        return wrapper
            .get(`[data-frame-id="${frameId}"]`)
            .getComponent<ComponentPublicInstance<PreviewProps>>({ name: 'test-preview' });
    }

    function pickerOf(frameId: string) {
        return wrapper.get(`[data-frame-id="${frameId}"]`).findComponent<ComponentPublicInstance<PreviewEntitySelectProps>>({
            name: 'test-preview-entity-select',
        });
    }

    it('passes a preview entity type only to frames whose root source is an entity type', async () => {
        await openFrames(
            [
                'product',
                'header',
                'swag-sidebar',
            ],
            'header',
        );

        expect(previewOf('frame-product').props('entityType')).toBe('product');
        expect(previewOf('frame-header').props('entityType')).toBeNull();
        expect(previewOf('frame-swag-sidebar').props('entityType')).toBeNull();
    });

    it('shows no preview entity picker for a frame without a preview entity type', async () => {
        await openFrames(['swag-sidebar'], 'swag-sidebar');

        expect(pickerOf('frame-swag-sidebar').exists()).toBe(false);
    });

    it('hides the preview entity picker when no entity types are available', async () => {
        await openFrames(['product'], 'product');
        await wrapper.setData({ availableLayoutTypes: [] });
        await flushPromises();

        expect(pickerOf('frame-product').exists()).toBe(false);
    });

    // Regression guard: an entity root source keeps its preview entity type and an enabled picker.
    it('gives an active product frame its entity type and an enabled picker', async () => {
        await openFrames(['product'], 'product');
        const picker = pickerOf('frame-product');

        expect(picker.props('entity')).toBe('product');
        expect(picker.props('value')).toBe('active-entity');
        expect(picker.props('disabled')).toBeFalsy();
        expect(previewOf('frame-product').props('entityType')).toBe('product');
    });
});
