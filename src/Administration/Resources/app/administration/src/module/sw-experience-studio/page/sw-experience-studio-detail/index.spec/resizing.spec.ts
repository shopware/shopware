import { shallowMount } from '@vue/test-utils';
import type { Component } from 'vue';
import detailComponent from '../index';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

describe('Experience Studio settings panel resizing', () => {
    let wrapper: ReturnType<typeof shallowMount> | null = null;

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
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
        const separator = wrapper.get('[role="separator"]');

        await separator.trigger('keydown', { key: 'ArrowLeft' });

        expect(separator.attributes('tabindex')).toBe('0');
        expect(separator.attributes('aria-valuemin')).toBe('320');
        expect(separator.attributes('aria-valuemax')).toBe('592');
        expect(separator.attributes('aria-valuenow')).toBe('340');
        expect((workspace.element as HTMLElement).style.getPropertyValue('--element-settings-width')).toBe('340px');

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
