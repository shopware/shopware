import { shallowMount } from '@vue/test-utils';
import type { Component } from 'vue';
import type { WorkspaceConfiguration } from '../../types/workspace.types';
import { createWorkspaceConfiguration } from '../../util/workspace-configuration.util';

async function createWrapper() {
    const configuration = createWorkspaceConfiguration();
    configuration.frames = [
        {
            id: 'preview',
            layoutId: 'layout',
            x: 100,
            y: -50,
            width: 700,
            height: 500,
            viewport: 'custom',
            previewEntityId: null,
        },
    ];
    configuration.canvas = { panX: 0, panY: 0, zoom: 0.5 };
    const wrapper = shallowMount((await wrapTestComponent('sw-experience-studio-canvas', { sync: true })) as Component, {
        props: {
            workspaceConfiguration: configuration,
            frames: [
                {
                    id: 'preview',
                    layout: { id: 'layout', name: 'Product page' },
                    viewport: 'custom',
                    selectedElementId: null,
                    previewEntityId: null,
                    isActive: false,
                },
            ],
        },
    });
    await flushPromises();

    return wrapper;
}

describe('Experience Studio frame border resizing', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('offers only left, right, and bottom resize controls', async () => {
        wrapper = await createWrapper();
        const handles = wrapper.findAll('.sw-experience-studio-canvas__resize-handle');

        expect(handles.map((handle) => handle.attributes('aria-label'))).toEqual([
            'sw-experience-studio.detail.canvas.resizeWest',
            'sw-experience-studio.detail.canvas.resizeEast',
            'sw-experience-studio.detail.canvas.resizeSouth',
        ]);
        expect(handles.every((handle) => handle.attributes('tabindex') === '-1')).toBe(true);
    });

    it.each([
        { edge: 'w', clientX: 40, clientY: 180, expected: { x: 40, y: -50, width: 820, height: 500 } },
        { edge: 'e', clientX: 160, clientY: 180, expected: { x: 160, y: -50, width: 820, height: 500 } },
        { edge: 's', clientX: 160, clientY: 180, expected: { x: 100, y: 30, width: 700, height: 660 } },
    ])(
        'selects the frame and resizes only the $edge dimension at 50% zoom',
        async ({ edge, clientX, clientY, expected }) => {
            wrapper = await createWrapper();
            await wrapper.get(`.sw-experience-studio-canvas__resize-handle.is--${edge}`).trigger('pointerdown', {
                button: 0,
                pointerId: 1,
                clientX: 100,
                clientY: 100,
            });
            const move = new MouseEvent('pointermove', { clientX, clientY });
            Object.defineProperty(move, 'pointerId', { value: 1 });
            document.dispatchEvent(move);
            document.dispatchEvent(new Event('pointerup'));
            await flushPromises();

            expect(wrapper.emitted('frame-activate')).toContainEqual(['preview']);
            expect(wrapper.emitted('frame-viewport-change')).toContainEqual(['preview', 'custom']);
            const configuration = wrapper.emitted<[WorkspaceConfiguration]>('configuration-change')?.at(-1)?.[0];
            expect(configuration?.frames[0]).toMatchObject({ id: 'preview', ...expected });
            expect(configuration?.canvas.zoom).toBe(0.5);
            expect(wrapper.classes()).not.toContain('is--gesturing');
        },
    );
});
