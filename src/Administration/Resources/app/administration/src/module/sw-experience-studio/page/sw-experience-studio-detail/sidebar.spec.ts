import { DOMWrapper, mount, shallowMount } from '@vue/test-utils';
import type { Component, ComponentPublicInstance } from 'vue';
import detailComponent from './index';
import '../../store/experience-studio-workspace.store';

async function createWrapper(renderMenus = false) {
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

    const mountComponent = renderMenus ? mount : shallowMount;

    return mountComponent(
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
                        props: ['entity', 'value'],
                        template: '<div class="test-preview-entity-select" />',
                    },
                    'sw-experience-studio-sidebar-tree': treeComponent,
                    'sw-experience-studio-sidebar-tree-node': treeNodeComponent,
                    'sw-experience-studio-preview': {
                        name: 'test-preview',
                        props: ['layout', 'entityId'],
                        template: `<button class="test-preview" :data-layout-id="layout.id" :data-entity-id="entityId" @click="$emit('select-element', layout.layout[0]?.id ?? null)">Select element</button>`,
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

describe('Experience Studio sidebar navigation', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    beforeEach(async () => {
        wrapper = await createWrapper();
        const stage = wrapper.get('.sw-experience-studio-canvas__stage').element;
        Object.defineProperties(stage, {
            clientWidth: { configurable: true, value: 1200 },
            clientHeight: { configurable: true, value: 800 },
        });
        jest.spyOn(stage, 'getBoundingClientRect').mockReturnValue({ width: 1200, height: 800 } as DOMRect);
        const layouts = [
            {
                id: 'layout-a',
                name: 'Home',
                layout: [{ id: 'element-a', component: 'Sw:Content:Text', properties: { text: 'Welcome' } }],
            },
            {
                id: 'layout-b',
                name: 'Summer collection',
                layout: [{ id: 'element-b', component: 'Sw:Content:Text', properties: { text: 'Summer' } }],
            },
        ];
        await wrapper.setData({
            activeLayoutId: 'layout-a',
            layout: layouts[0],
            openLayouts: layouts.map((layout) => ({
                id: layout.id,
                layout,
                selectedElementId: null,
                viewport: 'desktop',
                previewEntityId: null,
                settingsWidth: 320,
                savedState: '',
                isNew: false,
                isSaving: false,
                saveError: null,
            })),
        });
        await flushPromises();
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    it('changes preview entities through each layout frame toolbar', async () => {
        const frame = wrapper.get('[data-frame-id="layout-a"]');
        const selector = frame.getComponent<ComponentPublicInstance>({ name: 'test-preview-entity-select' });
        selector.vm.$emit('update:value', 'preview-category');
        await flushPromises();

        expect(frame.get('.test-preview').attributes('data-entity-id')).toBe('preview-category');
        expect(
            wrapper.get('[data-frame-id="layout-b"]').getComponent({ name: 'test-preview-entity-select' }).props('value'),
        ).toBeNull();
        expect(frame.findAll('button[aria-label="sw-experience-studio.detail.toolbar.undo"]')).toHaveLength(1);
        expect(frame.findAll('button[aria-label="sw-experience-studio.detail.toolbar.redo"]')).toHaveLength(1);
    });

    it('renders opened layouts in a compact Admin grid without selection or column headers', () => {
        const grid = wrapper.get('.sw-experience-studio-detail__open-layouts.sw-data-grid');

        expect(grid.classes()).toContain('is--compact');
        expect(grid.findAll('.sw-data-grid__body .sw-data-grid__row')).toHaveLength(2);
        expect(grid.findAll('.sw-data-grid__cell')).toHaveLength(2);
        expect(grid.find('.sw-data-grid__header').exists()).toBe(false);
        expect(grid.find('.sw-data-grid__cell--selection').exists()).toBe(false);
        expect(grid.find('.sw-data-grid__cell--actions').exists()).toBe(false);
        expect(grid.get('[aria-current="page"]').text()).toContain('Home');
    });

    it('shows a new layout name without a save reminder', async () => {
        await wrapper.setData({
            openLayouts: [
                {
                    id: 'new-layout',
                    layout: { id: 'new-layout', name: 'New layout', layout: [] },
                    selectedElementId: null,
                    viewport: 'desktop',
                    previewEntityId: null,
                    settingsWidth: 320,
                    savedState: '',
                    isNew: true,
                    isSaving: false,
                    saveError: null,
                },
            ],
        });

        const entry = wrapper.get('.sw-experience-studio-detail__layout-select');

        expect(entry.text()).toBe('New layout');
        expect(entry.find('small').exists()).toBe(false);
        expect(entry.find('.sw-experience-studio-detail__dirty-indicator').exists()).toBe(true);
    });

    it('opens the page tree when clicking the row outside its buttons', async () => {
        await wrapper.get('.sw-data-grid__row--1 .sw-data-grid__cell-content').trigger('click');

        expect(wrapper.get('.sw-experience-studio-sidebar-tree__header h3').text()).toBe('Summer collection');
    });

    it('opens the close confirmation without selecting the layout row', async () => {
        await wrapper.findAll('.sw-experience-studio-detail__layout-close')[1].trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-experience-studio-detail__open-layouts').exists()).toBe(true);
        expect(wrapper.find('.sw-experience-studio-sidebar-tree').exists()).toBe(false);
        expect(wrapper.get('sw-modal-stub').attributes('title')).toBe('sw-experience-studio.workspace.closeDirty.title');
        expect(wrapper.get('[aria-current="page"]').text()).toContain('Home');
    });

    it.each([
        { index: 0, modal: 'sw-experience-studio-layout-picker-stub' },
        { index: 1, modal: 'sw-experience-studio-create-wizard-stub' },
    ])('opens $modal from the free canvas context menu', async ({ index, modal }) => {
        wrapper.unmount();
        wrapper = await createWrapper(true);
        await flushPromises();
        await wrapper.get('.sw-experience-studio-canvas__background').trigger('contextmenu', {
            clientX: 500,
            clientY: 200,
        });
        await flushPromises();
        const menu = new DOMWrapper(document.body).get('.sw-experience-studio-canvas__context-menu');
        await menu.findAll('[role="menuitem"]')[index].trigger('click');
        await flushPromises();

        expect(wrapper.find(modal).exists()).toBe(true);
    });

    it('opens the selected page tree and returns to the opened pages with the back button', async () => {
        const sidebar = () => wrapper.get('.sw-experience-studio-canvas__sidebar');
        expect(sidebar().findAll('.sw-experience-studio-detail__layout-select')).toHaveLength(2);
        expect(sidebar().find('.sw-experience-studio-sidebar-tree').exists()).toBe(false);
        expect(
            wrapper.get('.sw-experience-studio-canvas__stage').find('aside.sw-experience-studio-canvas__sidebar').exists(),
        ).toBe(false);

        await sidebar().findAll('.sw-experience-studio-detail__layout-select')[1].trigger('click');
        await flushPromises();

        expect(sidebar().get('.sw-experience-studio-sidebar-tree__header h3').text()).toBe('Summer collection');
        expect(sidebar().find('.sw-experience-studio-canvas__panel-header').exists()).toBe(false);
        const treeHeader = sidebar().get('.sw-experience-studio-sidebar-tree__header');
        expect(treeHeader.element.firstElementChild).toBe(
            treeHeader.get('.sw-experience-studio-sidebar-tree__back-button').element,
        );
        expect(sidebar().find('.sw-experience-studio-detail__open-layouts').exists()).toBe(false);

        await sidebar().get('.sw-experience-studio-sidebar-tree__back-button').trigger('click');

        expect(sidebar().get('h2').text()).toBe('sw-experience-studio.workspace.layouts.title');
        expect(sidebar().findAll('.sw-experience-studio-detail__layout-select')).toHaveLength(2);
        expect(sidebar().find('.sw-experience-studio-sidebar-tree__back-button').exists()).toBe(false);

        // Selecting the already active page must open its tree again.
        await sidebar().findAll('.sw-experience-studio-detail__layout-select')[1].trigger('click');
        expect(sidebar().get('.sw-experience-studio-sidebar-tree__header h3').text()).toBe('Summer collection');
    });

    it.each([
        [
            0,
            'layout-a',
            'Home',
            150,
            -80,
        ],
        [
            1,
            'layout-b',
            'Summer collection',
            400,
            -200,
        ],
    ] as const)(
        'positions layout %s 20px from the canvas top-left at 100%% and opens its tree',
        async (index, id, name, x, y) => {
            const canvas = wrapper.getComponent({ ref: 'experienceStudioCanvas' });
            await canvas.setData({
                canvasZoom: 0.5,
                canvasPanX: 125,
                canvasPanY: 75,
                frameX: 150,
                frameY: -80,
                frameWidth: 700,
                frameHeight: 600,
                frameStates: {
                    'layout-a': { x: 150, y: -80, width: 700, height: 600 },
                    'layout-b': { x: 400, y: -200, width: 700, height: 600 },
                },
            });
            expect(wrapper.find('.sw-experience-studio-detail__layout-focus').exists()).toBe(false);

            await wrapper.findAll('.sw-experience-studio-detail__layout-select')[index].trigger('click');
            await flushPromises();

            expect(wrapper.get('.sw-experience-studio-canvas__world').attributes('style')).toContain(
                `translate3d(${-230 - x}px, ${-80 - y}px, 0) scale(1)`,
            );
            expect(wrapper.get('.sw-experience-studio-canvas__zoom-controls > span').text()).toBe('100%');
            expect(wrapper.get('.sw-experience-studio-sidebar-tree__header h3').text()).toBe(name);
            expect(wrapper.get(`[data-frame-id="${id}"]`).attributes('style')).toContain('width: 700px');
            expect(wrapper.get(`[data-frame-id="${id}"]`).attributes('style')).toContain('height: 600px');
        },
    );

    it('shows the overview title beside the open and create actions without an extra header', () => {
        const sidebar = wrapper.get('.sw-experience-studio-canvas__sidebar');
        const actions = sidebar.get('.sw-experience-studio-detail__layout-actions');

        expect(actions.get('h2').text()).toBe('sw-experience-studio.workspace.layouts.title');
        expect(actions.findAll('button')).toHaveLength(2);
        expect(sidebar.find('.sw-experience-studio-detail__layouts-empty-state').exists()).toBe(false);
        expect(sidebar.findAll('h2, h3')).toHaveLength(1);
        expect(sidebar.find('.sw-experience-studio-canvas__panel-header').exists()).toBe(false);
        expect(sidebar.find('[aria-label="sw-experience-studio.detail.canvas.settings"]').exists()).toBe(false);
    });

    it.each([
        { label: 'sw-experience-studio.workspace.layouts.create', icon: 'regular-plus', creates: true },
        { label: 'global.default.open', icon: 'regular-folder', creates: false },
    ])(
        'opens the layout action from the square $icon button with an accessible tooltip',
        async ({ label, icon, creates }) => {
            const actions = wrapper.get('.sw-experience-studio-detail__layout-actions');
            const button = actions.get(`button[aria-label="${label}"]`);

            expect(button.classes()).toEqual(expect.arrayContaining(['mt-button--secondary', 'mt-button--square']));
            expect(button.text()).toBe('');
            expect(button.get(`[data-testid="mt-icon__${icon}"]`).attributes('aria-hidden')).toBe('true');
            expect(button.attributes('tooltip-mock-message')).toBe(label);

            await button.trigger('click');
            await flushPromises();

            expect(wrapper.find('sw-experience-studio-create-wizard-stub').exists()).toBe(creates);
            expect(wrapper.find('sw-experience-studio-layout-picker-stub').exists()).toBe(!creates);
        },
    );

    it.each([
        { layoutId: 'layout-a', name: 'Home', elementId: 'element-a' },
        { layoutId: 'layout-b', name: 'Summer collection', elementId: 'element-b' },
    ])(
        'switches from the overview to the $name tree when selecting a preview element',
        async ({ layoutId, name, elementId }) => {
            const canvas = wrapper.getComponent({ ref: 'experienceStudioCanvas' });
            await canvas.setData({
                canvasZoom: 0.75,
                canvasPanX: 120,
                canvasPanY: 40,
                frameX: 0,
                frameY: 0,
                frameWidth: 400,
                frameHeight: 500,
                frameStates: {
                    'layout-a': { x: 0, y: 0, width: 400, height: 500 },
                    'layout-b': { x: 500, y: -100, width: 400, height: 500 },
                },
            });
            const cameraStyle = wrapper.get('.sw-experience-studio-canvas__world').attributes('style');
            expect(wrapper.find('.sw-experience-studio-detail__open-layouts').exists()).toBe(true);

            await wrapper.get(`.test-preview[data-layout-id="${layoutId}"]`).trigger('click');
            await flushPromises();

            expect(wrapper.find('.sw-experience-studio-detail__open-layouts').exists()).toBe(false);
            expect(wrapper.get('.sw-experience-studio-sidebar-tree__header h3').text()).toBe(name);
            expect(wrapper.find('.sw-experience-studio-sidebar-tree-node__element.is--active').exists()).toBe(true);
            expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(true);
            expect(wrapper.get('sw-experience-studio-element-settings-stub').attributes('selected-element-id')).toBe(
                elementId,
            );
            expect(wrapper.get('.sw-experience-studio-canvas__world').attributes('style')).toBe(cameraStyle);
        },
    );

    it('clears selection and closes settings on every layout switch, including previously selected layouts', async () => {
        const canvas = wrapper.getComponent({ ref: 'experienceStudioCanvas' });
        await wrapper.findAll('.sw-experience-studio-detail__layout-select')[0].trigger('click');
        await wrapper.get('.sw-experience-studio-sidebar-tree-node__element').trigger('click');

        for (const destination of [
            { index: 1, name: 'Summer collection', elementId: 'element-b' },
            { index: 0, name: 'Home', elementId: 'element-a' },
            { index: 1, name: 'Summer collection', elementId: 'element-b' },
        ]) {
            await wrapper.get('.sw-experience-studio-sidebar-tree__back-button').trigger('click');
            expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(true);
            await wrapper.findAll('.sw-experience-studio-detail__layout-select')[destination.index].trigger('click');
            await flushPromises();

            expect(wrapper.get('.sw-experience-studio-sidebar-tree__header h3').text()).toBe(destination.name);
            expect(wrapper.find('.sw-experience-studio-sidebar-tree-node__element.is--active').exists()).toBe(false);
            expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);
            expect(canvas.props('frames')).toEqual([
                expect.objectContaining({ id: 'layout-a', selectedElementId: null }),
                expect.objectContaining({ id: 'layout-b', selectedElementId: null }),
            ]);

            await wrapper.get('.sw-experience-studio-sidebar-tree-node__element').trigger('click');
            expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(true);
            expect(wrapper.get('sw-experience-studio-element-settings-stub').attributes('selected-element-id')).toBe(
                destination.elementId,
            );
        }
    });

    it.each(['tree', 'preview'])(
        'opens the corresponding settings from the %s without a settings button',
        async (source) => {
            await wrapper.findAll('.sw-experience-studio-detail__layout-select')[0].trigger('click');
            expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);

            if (source === 'tree') {
                await wrapper.get('.sw-experience-studio-sidebar-tree-node__element').trigger('click');
            } else {
                await wrapper.get('.test-preview').trigger('click');
            }
            await flushPromises();

            expect(wrapper.get('.sw-experience-studio-sidebar-tree__header h3').text()).toBe('Home');
            expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(true);
            expect(wrapper.get('sw-experience-studio-element-settings-stub').attributes('selected-element-id')).toBe(
                'element-a',
            );

            await wrapper.get('.sw-experience-studio-sidebar-tree__back-button').trigger('click');
            expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(true);

            await wrapper.get('.sw-experience-studio-canvas__close-panel').trigger('click');
            expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);
            expect(wrapper.find('.sw-experience-studio-detail__open-layouts').exists()).toBe(true);
        },
    );

    it.each([
        { label: 'sw-experience-studio.workspace.layoutPicker.open', creates: false },
        { label: 'sw-experience-studio.general.createNewLayout', creates: true },
    ])('opens the correct dialog from the empty-state $label action', async ({ label, creates }) => {
        await wrapper.setData({ openLayouts: [], activeLayoutId: null, layout: null });
        const emptyState = wrapper.get('.sw-experience-studio-detail__layouts-empty-state');
        const buttons = emptyState.findAll('button');
        const button = buttons[creates ? 1 : 0];

        expect(buttons).toHaveLength(2);
        expect(button.text()).toBe(label);
        await button.trigger('click');
        await flushPromises();

        expect(wrapper.find('sw-experience-studio-create-wizard-stub').exists()).toBe(creates);
        expect(wrapper.find('sw-experience-studio-layout-picker-stub').exists()).toBe(!creates);
    });

    it('returns to the overview when the last page is closed', async () => {
        await wrapper.findAll('.sw-experience-studio-detail__layout-select')[0].trigger('click');
        await wrapper.setData({ openLayouts: [], activeLayoutId: null, layout: null });

        expect(wrapper.get('.sw-experience-studio-canvas__sidebar h2').text()).toBe(
            'sw-experience-studio.workspace.layouts.title',
        );
        expect(wrapper.find('.sw-experience-studio-sidebar-tree__back-button').exists()).toBe(false);
        expect(wrapper.find('.sw-experience-studio-detail__layout-actions').exists()).toBe(true);
        expect(wrapper.find('.sw-experience-studio-detail__open-layouts').exists()).toBe(false);
        const emptyState = wrapper.get('.sw-experience-studio-detail__layouts-empty-state');
        expect(emptyState.get('h2').text()).toBe('sw-experience-studio.workspace.layouts.emptyTitle');
        expect(emptyState.text()).toContain('sw-experience-studio.workspace.layouts.emptyDescription');
    });
});
