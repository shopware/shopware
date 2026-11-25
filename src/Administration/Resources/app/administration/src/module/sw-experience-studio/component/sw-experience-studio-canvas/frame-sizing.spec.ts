import { shallowMount } from '@vue/test-utils';
import type { Component } from 'vue';
import type { Viewport } from './canvas.config';

async function createWrapper(canvasHeight: number, viewport: Viewport = 'desktop') {
    const wrapper = shallowMount((await wrapTestComponent('sw-experience-studio-canvas', { sync: true })) as Component, {
        props: {
            frames: [
                {
                    id: 'preview',
                    layout: { id: 'layout', name: 'Product page' },
                    selectedElementId: null,
                    previewEntityId: null,
                    viewport,
                    isActive: true,
                },
            ],
        },
    });
    const workspace = wrapper.get('.sw-experience-studio-canvas__stage').element;
    Object.defineProperties(workspace, {
        clientWidth: { value: 2000 },
        clientHeight: { value: canvasHeight },
    });
    jest.spyOn(workspace, 'getBoundingClientRect').mockReturnValue({ width: 2000, height: canvasHeight } as DOMRect);
    await flushPromises();

    return wrapper;
}

describe('Experience Studio default frame sizing', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    it.each([
        { viewport: 'desktop', canvasHeight: 800, frameHeight: 604 },
        { viewport: 'tablet-landscape', canvasHeight: 1000, frameHeight: 804 },
        { viewport: 'mobile', canvasHeight: 600, frameHeight: 404 },
    ] as const)('fits new $viewport previews and their toolbar at 100%', async ({ viewport, canvasHeight, frameHeight }) => {
        wrapper = await createWrapper(canvasHeight, viewport);
        const frame = wrapper.get('[data-frame-id="preview"]').element as HTMLElement;

        expect(frame.style.height).toBe(`${frameHeight}px`);
        expect(wrapper.get('.sw-experience-studio-canvas__zoom-controls > span').text()).toBe('100%');
    });

    it.each([
        'viewportMobile',
        'viewportTablet',
        'viewportDesktop',
    ])('restores the fitting height after a custom resize when selecting %s', async (preset) => {
        wrapper = await createWrapper(800);
        const frame = wrapper.get('[data-frame-id="preview"]').element as HTMLElement;
        await wrapper.get('.sw-experience-studio-canvas__resize-handle.is--s').trigger('pointerdown', {
            button: 0,
            pointerId: 1,
            clientY: 0,
        });
        const move = new MouseEvent('pointermove', { clientY: 200 });
        Object.defineProperty(move, 'pointerId', { value: 1 });
        document.dispatchEvent(move);
        document.dispatchEvent(new Event('pointerup'));
        await flushPromises();
        expect(frame.style.height).toBe('804px');

        await wrapper.get(`button[aria-label="sw-experience-studio.detail.toolbar.${preset}"]`).trigger('click');

        expect(frame.style.height).toBe('604px');
    });

    it.each([
        { canvasHeight: 300, frameHeight: 280 },
        { canvasHeight: 3000, frameHeight: 1800 },
    ])('keeps the frame height limits for a $canvasHeight pixel canvas', async ({ canvasHeight, frameHeight }) => {
        wrapper = await createWrapper(canvasHeight);

        expect((wrapper.get('[data-frame-id="preview"]').element as HTMLElement).style.height).toBe(`${frameHeight}px`);
    });
});
