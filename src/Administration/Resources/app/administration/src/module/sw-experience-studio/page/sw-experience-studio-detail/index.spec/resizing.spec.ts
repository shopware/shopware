import { shallowMount } from '@vue/test-utils';
import type { Component } from 'vue';
import canvasComponent from '../../../component/sw-experience-studio-canvas';
import detailComponent from '../index';
import '../../../store/experience-studio-workspace.store';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

describe('Experience Studio settings panel resizing', () => {
    let wrapper: ReturnType<typeof shallowMount> | null = null;

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        Shopware.Store.get('experienceStudioEditor').reset();
        jest.restoreAllMocks();
    });

    const createVm = (workspaceWidth: number) => ({
        selectedElementHasRichText: true,
        elementSettingsWidth: 320,
        isResizingElementSettings: true,
        resizeStartX: 500,
        resizeStartWidth: 320,
        getElementSettingsMaxWidth: methods.getElementSettingsMaxWidth,
        setElementSettingsWidth: methods.setElementSettingsWidth,
        $refs: {
            workspace: {
                getBoundingClientRect: () => ({ width: workspaceWidth }),
            },
        },
    });

    it('opens settings when an element is selected', () => {
        const openSettingsPanel = jest.fn();
        const vm = {
            selectedElementId: null,
            syncActiveLayoutState: jest.fn(),
            $refs: {
                experienceStudioCanvas: { openSettingsPanel },
            },
        };

        methods.onElementSelect.call(vm, 'element-id');

        expect(vm.selectedElementId).toBe('element-id');
        expect(openSettingsPanel).toHaveBeenCalledTimes(1);
    });

    it('resets the settings panel width when it closes', () => {
        const vm = {
            elementSettingsWidth: 640,
        };

        methods.onSettingsPanelClose.call(vm);

        expect(vm.elementSettingsWidth).toBe(320);
    });

    it('resizes the element settings panel while preserving a minimum preview width', () => {
        const vm = {
            isResizingElementSettings: true,
            resizeStartX: 500,
            resizeStartWidth: 320,
            elementSettingsWidth: 320,
            getElementSettingsMaxWidth: methods.getElementSettingsMaxWidth,
            setElementSettingsWidth: methods.setElementSettingsWidth,
            $refs: {
                workspace: {
                    getBoundingClientRect: () => ({ width: 1200 }),
                },
            },
        };

        methods.onElementSettingsResizeMove.call(vm, { clientX: 200 } as PointerEvent);
        expect(vm.elementSettingsWidth).toBe(592);

        methods.onElementSettingsResizeMove.call(vm, { clientX: -500 } as PointerEvent);
        expect(vm.elementSettingsWidth).toBe(592);

        methods.onElementSettingsResizeMove.call(vm, { clientX: 1000 } as PointerEvent);
        expect(vm.elementSettingsWidth).toBe(320);
    });

    it('captures the pointer for the entire resize gesture', () => {
        const removeDocumentListener = jest.spyOn(document, 'removeEventListener');
        const removeWindowListener = jest.spyOn(window, 'removeEventListener');
        const vm = {
            elementSettingsWidth: 320,
            resizeStartX: 500,
            resizeStartWidth: 320,
            isResizingElementSettings: false,
            resizeMoveHandler: null,
            resizeEndHandler: null,
            resizeHandle: null,
            resizePointerId: null,
            onElementSettingsResizeMove: methods.onElementSettingsResizeMove,
            stopElementSettingsResize: methods.stopElementSettingsResize,
        };
        const event = {
            clientX: 500,
            pointerId: 1,
            currentTarget: {
                setPointerCapture: jest.fn(),
                hasPointerCapture: jest.fn().mockReturnValue(true),
                releasePointerCapture: jest.fn(),
            },
            preventDefault: jest.fn(),
        } as unknown as PointerEvent;

        methods.onElementSettingsResizeStart.call(vm, event);
        expect(vm.isResizingElementSettings).toBe(true);
        const setPointerCapture = Reflect.get(event.currentTarget as HTMLElement, 'setPointerCapture') as jest.Mock;
        expect(setPointerCapture).toHaveBeenCalledWith(1);

        const resizeMoveHandler = vm.resizeMoveHandler;
        const resizeEndHandler = vm.resizeEndHandler as unknown as () => void;
        resizeEndHandler();
        expect(Reflect.get(event.currentTarget as HTMLElement, 'releasePointerCapture')).toHaveBeenCalledWith(1);
        expect(removeDocumentListener).toHaveBeenCalledWith('pointermove', resizeMoveHandler);
        expect(removeDocumentListener).toHaveBeenCalledWith('pointerup', resizeEndHandler);
        expect(removeDocumentListener).toHaveBeenCalledWith('pointercancel', resizeEndHandler);
        expect(removeWindowListener).toHaveBeenCalledWith('blur', resizeEndHandler);

        expect(vm.isResizingElementSettings).toBe(false);
        expect(vm.resizeEndHandler).toBeNull();
    });

    it('grows the settings panel for elements with rich-text properties', () => {
        const vm = {
            selectedElementHasRichText: true,
            elementSettingsWidth: 320,
            getElementSettingsMaxWidth: methods.getElementSettingsMaxWidth,
            setElementSettingsWidth: methods.setElementSettingsWidth,
            $refs: {},
        };

        methods.adjustElementSettingsWidth.call(vm);

        expect(vm.elementSettingsWidth).toBe(640);
    });

    it.each([
        [
            1600,
            640,
            800,
        ],
        [
            1248,
            640,
            640,
        ],
        [
            1200,
            592,
            592,
        ],
        [
            1000,
            392,
            392,
        ],
        [
            928,
            320,
            320,
        ],
        [
            800,
            320,
            320,
        ],
    ])('bounds automatic, pointer and keyboard sizing in a %spx workspace', (workspaceWidth, automaticWidth, maximum) => {
        const vm = createVm(workspaceWidth);

        methods.adjustElementSettingsWidth.call(vm);
        expect(vm.elementSettingsWidth).toBe(automaticWidth);

        methods.onElementSettingsResizeMove.call(vm, { clientX: -1000 } as PointerEvent);
        expect(vm.elementSettingsWidth).toBe(maximum);

        methods.onElementSettingsResizeKeydown.call(vm, new KeyboardEvent('keydown', { key: 'ArrowLeft' }));
        expect(vm.elementSettingsWidth).toBe(maximum);

        methods.onElementSettingsResizeMove.call(vm, { clientX: 1500 } as PointerEvent);
        expect(vm.elementSettingsWidth).toBe(320);

        methods.onElementSettingsResizeKeydown.call(vm, new KeyboardEvent('keydown', { key: 'ArrowRight' }));
        expect(vm.elementSettingsWidth).toBe(320);
    });

    it('clamps an existing width when switching to an ordinary element in a narrower workspace', () => {
        const vm = { ...createVm(1000), selectedElementHasRichText: false, elementSettingsWidth: 640 };

        methods.adjustElementSettingsWidth.call(vm);

        expect(vm.elementSettingsWidth).toBe(392);
    });

    it('does not change the width when no pointer resize is active', () => {
        const vm = { ...createVm(1200), isResizingElementSettings: false };

        methods.onElementSettingsResizeMove.call(vm, { clientX: 200 } as PointerEvent);

        expect(vm.elementSettingsWidth).toBe(320);
    });

    it.each([
        [1600, 640],
        [1000, 392],
    ])(
        'resets preview settings widths while keeping text editing wide in a %spx workspace',
        async (workspaceWidth, expectedWidth) => {
            const layout = {
                id: 'shared-layout',
                name: 'Shared layout',
                layout: [
                    { id: 'text', component: 'Sw:Content:Text' },
                    { id: 'media', component: 'Sw:Media:Image' },
                ],
            };
            const textType = { properties: { text: { type: 'string', adminUI: { component: 'mt-text-editor' } } } };
            const mediaType = { properties: { media: { type: 'string', adminUI: { component: 'sw-media-field' } } } };
            wrapper = shallowMount(
                {
                    ...detailComponent,
                    created() {},
                    mounted() {},
                    beforeUnmount() {},
                    computed: {
                        ...detailComponent.computed,
                        selectedElementType(this: { selectedElementId: string | null }) {
                            if (this.selectedElementId === 'text') {
                                return textType;
                            }
                            return this.selectedElementId === 'media' ? mediaType : null;
                        },
                    },
                    methods: { ...detailComponent.methods, diagnoseLayout: jest.fn() },
                } as Component,
                {
                    data: () => ({
                        layout,
                        activeFrameId: 'first',
                        openLayouts: [
                            { id: layout.id, layout, savedState: '', isNew: false, isSaving: false, saveError: null },
                        ],
                        frames: ['first', 'second'].map((id) => ({
                            id,
                            layoutId: layout.id,
                            selectedElementId: null,
                            viewport: 'desktop',
                            previewEntityId: null,
                        })),
                    }),
                    global: {
                        provide: { acl: { can: () => true }, repositoryFactory: { create: jest.fn() } },
                        mocks: { $route: { params: {} } },
                        stubs: {
                            'sw-page': { template: '<div><slot name="content" /></div>' },
                            'sw-experience-studio-canvas': canvasComponent,
                            'sw-experience-studio-toolbar': true,
                            'sw-experience-studio-layout-tree': true,
                            'sw-experience-studio-sidebar-tree': true,
                            'sw-experience-studio-frame-menu': true,
                            'sw-experience-studio-element-settings': true,
                            'sw-experience-studio-element-picker': true,
                            'sw-experience-studio-preview': {
                                emits: ['select-element'],
                                template: `<div>
                                <button class="test-preview" @click="$emit('select-element', 'text')">Select text</button>
                                <button class="test-media" @click="$emit('select-element', 'media')">Select media</button>
                            </div>`,
                            },
                        },
                    },
                },
            );
            jest.spyOn(
                wrapper.get('.sw-experience-studio-detail__workspace').element,
                'getBoundingClientRect',
            ).mockReturnValue({
                width: workspaceWidth,
            } as DOMRect);
            jest.spyOn(wrapper.get('.sw-experience-studio-canvas__stage').element, 'getBoundingClientRect').mockReturnValue({
                left: 0,
                top: 0,
                width: 1200,
                height: 800,
            } as DOMRect);
            await wrapper.getComponent(canvasComponent as Component).setData({ canvasZoom: 0.25 });
            await flushPromises();

            for (const frameId of [
                'first',
                'second',
                'first',
                'second',
            ]) {
                await wrapper.get(`[data-frame-id="${frameId}"] .test-preview`).trigger('click');
                await flushPromises();

                expect(
                    (wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width,
                ).toBe(`${expectedWidth}px`);
            }

            for (const clickCanvas of [false, true]) {
                await wrapper.get('button[aria-label="sw-experience-studio.detail.canvas.closePanel"]').trigger('click');
                expect(wrapper.find('.sw-experience-studio-canvas__panel--settings').exists()).toBe(false);
                if (clickCanvas) {
                    await wrapper.get('.sw-experience-studio-canvas__background').trigger('pointerdown', {
                        button: 0,
                        pointerId: 1,
                        clientX: 100,
                        clientY: 100,
                    });
                    document.dispatchEvent(new Event('pointerup'));
                    await wrapper.get('.sw-experience-studio-canvas__background').trigger('click');
                }
                await wrapper.get('[data-frame-id="second"] .test-preview').trigger('click');
                expect(
                    (wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width,
                ).toBe(`${expectedWidth}px`);

                await wrapper.get('[role="separator"]').trigger('keydown', { key: 'ArrowRight' });
                await wrapper.get('[data-frame-id="second"] .test-preview').trigger('click');
                expect(
                    (wrapper.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width,
                ).toBe(`${expectedWidth - 20}px`);
            }

            await wrapper.get('[data-frame-id="first"] .test-preview').trigger('click');
            await wrapper.get('button[aria-label="sw-experience-studio.detail.canvas.closePanel"]').trigger('click');
            await wrapper.get('[data-frame-id="second"]').trigger('click');
            await wrapper.get('[data-frame-id="first"]').trigger('click');
            await wrapper.get('[data-frame-id="first"] .test-media').trigger('click');
            const view = wrapper;
            const sidebar = () => view.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement;
            expect(sidebar().style.width).toBe('320px');

            await wrapper.get('[role="separator"]').trigger('keydown', { key: 'ArrowLeft' });
            await wrapper.get('[data-frame-id="first"] .test-media').trigger('click');
            expect(sidebar().style.width).toBe('340px');

            await wrapper.get('[data-frame-id="second"] .test-media').trigger('click');
            expect(sidebar().style.width).toBe('320px');
            await wrapper.get('[data-frame-id="first"] .test-media').trigger('click');
            expect(sidebar().style.width).toBe('320px');
        },
    );

    it('supports keyboard resizing on the rendered separator and announces its actual bounds', async () => {
        wrapper = shallowMount(
            {
                ...detailComponent,
                created() {},
                mounted() {},
                beforeUnmount() {},
            } as Component,
            {
                global: {
                    provide: {
                        acl: { can: () => true },
                        repositoryFactory: { create: jest.fn() },
                    },
                    mocks: { $route: { name: 'sw.experience.studio.detail', params: { id: 'layout-1' } } },
                    stubs: {
                        'sw-page': { template: '<div><slot name="content" /></div>' },
                        'sw-experience-studio-canvas': canvasComponent,
                        'sw-experience-studio-toolbar': true,
                        'sw-experience-studio-sidebar-tree': true,
                        'sw-experience-studio-preview': true,
                        'sw-experience-studio-element-settings': true,
                        'sw-experience-studio-element-picker': true,
                    },
                },
            },
        );
        const workspace = wrapper.get('.sw-experience-studio-detail__workspace');
        jest.spyOn(workspace.element, 'getBoundingClientRect').mockReturnValue({ width: 1200 } as DOMRect);
        const canvas = wrapper.findComponent(canvasComponent as Component);
        await canvas.setData({ isSettingsOpen: true });
        const separator = canvas.get('[role="separator"]');

        await separator.trigger('keydown', { key: 'ArrowLeft' });

        expect(separator.attributes('tabindex')).toBe('0');
        expect(separator.attributes('aria-valuemin')).toBe('320');
        expect(separator.attributes('aria-valuemax')).toBe('592');
        expect(separator.attributes('aria-valuenow')).toBe('340');
        expect((canvas.get('.sw-experience-studio-canvas__panel--settings').element as HTMLElement).style.width).toBe(
            '340px',
        );

        await separator.trigger('keydown', { key: 'ArrowRight' });
        expect(separator.attributes('aria-valuenow')).toBe('320');

        const unrelatedKey = new KeyboardEvent('keydown', { key: 'Tab', cancelable: true });
        separator.element.dispatchEvent(unrelatedKey);
        expect(unrelatedKey.defaultPrevented).toBe(false);
        expect(separator.attributes('aria-valuenow')).toBe('320');

        const resizeKey = new KeyboardEvent('keydown', { key: 'ArrowLeft', cancelable: true });
        separator.element.dispatchEvent(resizeKey);
        expect(resizeKey.defaultPrevented).toBe(true);
    });
});
