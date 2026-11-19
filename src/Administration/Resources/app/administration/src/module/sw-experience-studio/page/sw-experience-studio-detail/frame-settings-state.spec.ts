import { shallowMount } from '@vue/test-utils';
import type { Component, ComponentPublicInstance } from 'vue';
import detailComponent from './index';
import '../../store/experience-studio-workspace.store';

type SettingsPanel = 'element' | 'translations';

async function createWrapper() {
    const layouts = ['a', 'b'].map((id) => ({
        id: `layout-${id}`,
        name: `Layout ${id}`,
        rootSource: 'product',
        layout: [{ id: `text-${id}`, component: 'Test:Text', properties: { text: id } }],
    }));
    const canvas = await wrapTestComponent('sw-experience-studio-canvas', { sync: true });
    Shopware.Store.get('experienceStudioEditor').initialize(layouts[0].id);
    const wrapper = shallowMount(
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
            data: () => ({
                layout: layouts[0],
                activeFrameId: 'first-preview',
                openLayouts: layouts.map((layout) => ({
                    id: layout.id,
                    layout,
                    savedState: JSON.stringify({ name: layout.name, rootSource: layout.rootSource, layout: layout.layout }),
                    isNew: false,
                    isSaving: false,
                    saveError: null,
                })),
                frames: [
                    { id: 'first-preview', layoutId: 'layout-a' },
                    { id: 'second-preview', layoutId: 'layout-a' },
                    { id: 'other-layout', layoutId: 'layout-b' },
                ].map((frame) => ({
                    ...frame,
                    selectedElementId: null,
                    viewport: 'desktop',
                    previewEntityId: null,
                    languageId: Shopware.Defaults.systemLanguageId,
                })),
            }),
            global: {
                provide: { acl: { can: () => true }, repositoryFactory: { create: () => ({}) } },
                mocks: { $route: { params: {} } },
                stubs: {
                    'sw-page': { template: '<div><slot name="content" /></div>' },
                    'sw-experience-studio-canvas': canvas,
                    'sw-experience-studio-frame-menu': await wrapTestComponent('sw-experience-studio-frame-menu', {
                        sync: true,
                    }),
                    'sw-experience-studio-toolbar': true,
                    'sw-experience-studio-layout-tree': true,
                    'sw-experience-studio-sidebar-tree': true,
                    'sw-experience-studio-element-settings': true,
                    'sw-experience-studio-translation-settings': true,
                    'sw-experience-studio-element-picker': true,
                    'sw-entity-single-select': true,
                    'sw-experience-studio-preview': {
                        props: ['layout'],
                        template:
                            '<button class="test-select-element" @click="$emit(\'select-element\', layout.layout[0].id)">Select element</button>',
                    },
                },
            },
        },
    );
    jest.spyOn(wrapper.get('.sw-experience-studio-detail__workspace').element, 'getBoundingClientRect').mockReturnValue({
        width: 1600,
    } as DOMRect);
    jest.spyOn(wrapper.get('.sw-experience-studio-canvas__stage').element, 'getBoundingClientRect').mockReturnValue({
        width: 2000,
        height: 1200,
    } as DOMRect);
    await wrapper.getComponent<ComponentPublicInstance>({ ref: 'experienceStudioCanvas' }).setData({ canvasZoom: 0.2 });
    await flushPromises();

    return wrapper;
}

describe('Experience Studio settings follow the active frame', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        wrapper?.unmount();
        Shopware.Store.get('experienceStudioEditor').reset();
        jest.restoreAllMocks();
    });

    async function openSettings(frameId: string, panel: SettingsPanel) {
        const frame = wrapper.get(`[data-frame-id="${frameId}"]`);
        await frame
            .get(panel === 'element' ? '.test-select-element' : '.sw-experience-studio-frame-menu__translate')
            .trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(true);
    }

    it.each([
        { panel: 'element', destination: 'second-preview' },
        { panel: 'element', destination: 'other-layout' },
        { panel: 'translations', destination: 'second-preview' },
        { panel: 'translations', destination: 'other-layout' },
    ] as const)(
        'clears $panel settings when leaving a frame for $destination and returning repeatedly',
        async ({ panel, destination }) => {
            wrapper = await createWrapper();
            await openSettings('first-preview', panel);

            for (const frameId of [
                destination,
                'first-preview',
                destination,
                'first-preview',
            ]) {
                await wrapper.get('[role="separator"]').trigger('keydown', { key: 'ArrowLeft' });
                await wrapper.get(`[data-frame-id="${frameId}"] .sw-experience-studio-canvas__move-handle`).trigger('click');
                await flushPromises();

                expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);
                const canvas = wrapper.getComponent<
                    ComponentPublicInstance<{ frames: { selectedElementId: string | null }[] }>
                >({ ref: 'experienceStudioCanvas' });
                expect(canvas.props('frames').every((frame) => frame.selectedElementId === null)).toBe(true);

                await openSettings(frameId, panel);
                expect(
                    (wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width,
                ).toBe('320px');
            }
        },
    );

    it.each(['element', 'translations'] as const)(
        'keeps %s settings open when reselecting the already active frame',
        async (panel) => {
            wrapper = await createWrapper();
            await openSettings('first-preview', panel);
            await wrapper.get('[role="separator"]').trigger('keydown', { key: 'ArrowLeft' });
            const initialWidth = (wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style
                .width;

            await wrapper.get('[data-frame-id="first-preview"] .sw-experience-studio-canvas__move-handle').trigger('click');
            await flushPromises();

            expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(true);
            expect((wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width).toBe(
                initialWidth,
            );
        },
    );
});
