import { shallowMount } from '@vue/test-utils';
import type { Component } from 'vue';
import type { WorkspaceConfiguration } from '../../types/workspace.types';

type Frame = { id: string; x: number; y: number; width: number; height: number };

async function createWrapper(frames: Frame[] = [], width = 1200, height = 800) {
    const wrapper = shallowMount((await wrapTestComponent('sw-experience-studio-canvas', { sync: true })) as Component, {
        slots: { preview: '<div class="test-preview"></div>' },
        props: {
            frames: frames.map((frame) => ({
                id: frame.id,
                layout: { id: frame.id, name: frame.id },
                selectedElementId: null,
                viewport: 'desktop',
                previewEntityId: null,
                isActive: false,
            })),
            workspaceConfiguration: {
                frames: frames.map(({ id, ...bounds }) => ({
                    layoutId: id,
                    ...bounds,
                    viewport: 'desktop',
                    previewEntityId: null,
                })),
                previewSalesChannelId: null,
                canvas: { panX: 0, panY: 0, zoom: 1 },
            },
        },
    });
    await flushPromises();
    const stage = wrapper.get('.sw-experience-studio-canvas__stage').element;
    Object.defineProperties(stage, {
        clientWidth: { value: width },
        clientHeight: { value: height },
    });
    jest.spyOn(stage, 'getBoundingClientRect').mockReturnValue({ width, height } as DOMRect);
    await wrapper.setData({ canvasZoom: 0.75, canvasPanX: 500, canvasPanY: -300 });

    return wrapper;
}

const resetLabel = 'sw-experience-studio.detail.canvas.resetZoom';
const fitLabel = 'sw-experience-studio.detail.canvas.fit';

describe('Experience Studio canvas zoom toolbar', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    it('places zoom to fit next to the 100% action and provides accessible tooltips', async () => {
        wrapper = await createWrapper();
        const reset = wrapper.get(`button[aria-label="${resetLabel}"]`);
        const fit = wrapper.get(`button[aria-label="${fitLabel}"]`);

        const dividers = wrapper.findAll('.sw-experience-studio-canvas__zoom-divider');
        expect(dividers).toHaveLength(2);
        expect(reset.element.previousElementSibling).toBe(dividers[0].element);
        expect(reset.element.nextElementSibling).toBe(dividers[1].element);
        expect(fit.element.previousElementSibling).toBe(dividers[1].element);
        expect(reset.attributes('tooltip-mock-message')).toBe(resetLabel);
        expect(fit.attributes('tooltip-mock-message')).toBe(fitLabel);
        expect(fit.find('[data-testid="mt-icon__regular-expand-arrows"]').exists()).toBe(true);
    });

    it.each([
        {
            direction: 'horizontal',
            width: 1200,
            height: 800,
            frames: [
                { id: 'left', x: -1800, y: 200, width: 1480, height: 900 },
                { id: 'right', x: 2000, y: -400, width: 375, height: 900 },
            ],
        },
        {
            direction: 'vertical',
            width: 800,
            height: 1000,
            frames: [
                { id: 'top', x: -200, y: -1500, width: 768, height: 640 },
                { id: 'bottom', x: 300, y: 2200, width: 375, height: 900 },
            ],
        },
        {
            direction: 'widely separated horizontal',
            width: 800,
            height: 700,
            frames: [
                { id: 'left', x: -5000, y: 0, width: 1480, height: 900 },
                { id: 'right', x: 5000, y: 0, width: 1480, height: 900 },
            ],
        },
        {
            direction: 'widely separated vertical',
            width: 800,
            height: 700,
            frames: [
                { id: 'top', x: 0, y: -5000, width: 1480, height: 900 },
                { id: 'bottom', x: 0, y: 5000, width: 1480, height: 900 },
            ],
        },
    ])('fits all $direction layouts into the canvas, including off-screen frames', async ({ frames, width, height }) => {
        wrapper = await createWrapper(frames, width, height);
        expect(wrapper.findAll('[data-frame-id]')).toHaveLength(frames.length);
        expect(wrapper.findAll('.test-preview').length).toBeLessThan(frames.length);
        await wrapper.get(`button[aria-label="${fitLabel}"]`).trigger('click');

        expect(wrapper.findAll('[data-frame-id]')).toHaveLength(frames.length);
        expect(wrapper.findAll('.test-preview')).toHaveLength(frames.length);
        const transform = wrapper.get('.sw-experience-studio-canvas__world').attributes('style') ?? '';
        const match = /translate3d\(([-\d.]+)px, ([-\d.]+)px, 0\) scale\(([-\d.]+)\)/.exec(transform);
        expect(match).not.toBeNull();
        const [
            ,
            panX,
            panY,
            zoom,
        ] = match!.map(Number);
        const projected = frames.map((frame) => ({
            left: width / 2 + panX + (frame.x - frame.width / 2) * zoom,
            right: width / 2 + panX + (frame.x + frame.width / 2) * zoom,
            top: height / 2 + panY + (frame.y - frame.height / 2) * zoom,
            bottom: height / 2 + panY + (frame.y + frame.height / 2) * zoom,
        }));
        const left = Math.min(...projected.map((frame) => frame.left));
        const right = Math.max(...projected.map((frame) => frame.right));
        const top = Math.min(...projected.map((frame) => frame.top));
        const bottom = Math.max(...projected.map((frame) => frame.bottom));

        expect(left).toBeGreaterThanOrEqual(48 - 0.001);
        expect(top).toBeGreaterThanOrEqual(48 - 0.001);
        expect(right).toBeLessThanOrEqual(width - 48 + 0.001);
        expect(bottom).toBeLessThanOrEqual(height - 48 + 0.001);
        expect((left + right) / 2).toBeCloseTo(width / 2);
        expect((top + bottom) / 2).toBeCloseTo(height / 2);
        expect(Math.min(left, top)).toBeCloseTo(48);
    });

    it('keeps zoom controls usable below 10% and emits a restorable workspace zoom', async () => {
        wrapper = await createWrapper(
            [
                { id: 'left', x: -5000, y: 200, width: 1480, height: 900 },
                { id: 'right', x: 5000, y: 200, width: 1480, height: 900 },
            ],
            800,
            700,
        );
        await wrapper.get(`button[aria-label="${fitLabel}"]`).trigger('click');
        const fittedZoom = 704 / 11480;
        const zoomOut = wrapper.get('button[aria-label="sw-experience-studio.detail.canvas.zoomOut"]');

        expect(zoomOut.attributes('disabled')).toBeUndefined();
        await zoomOut.trigger('click');
        expect(wrapper.emitted('configuration-change')?.at(-1)?.[0]).toMatchObject({
            canvas: { zoom: fittedZoom / 2 },
        });
        await wrapper.get('button[aria-label="sw-experience-studio.detail.canvas.zoomIn"]').trigger('click');
        expect(wrapper.emitted('configuration-change')?.at(-1)?.[0]).toMatchObject({
            canvas: { zoom: fittedZoom / 2 + 0.1 },
        });

        await wrapper.get(`button[aria-label="${fitLabel}"]`).trigger('click');
        const savedConfiguration = wrapper.emitted<[WorkspaceConfiguration]>('configuration-change')?.at(-1)?.[0];
        const fittedTransform = wrapper.get('.sw-experience-studio-canvas__world').attributes('style');
        expect(savedConfiguration).toMatchObject({ canvas: { zoom: fittedZoom } });
        wrapper.unmount();
        wrapper = shallowMount((await wrapTestComponent('sw-experience-studio-canvas', { sync: true })) as Component, {
            props: {
                workspaceConfiguration: savedConfiguration,
                frames: savedConfiguration?.frames.map((frame) => ({
                    id: frame.layoutId,
                    layout: { name: frame.layoutId },
                    viewport: frame.viewport,
                    previewEntityId: null,
                    isActive: false,
                })),
            },
        });
        await flushPromises();

        expect(wrapper.get('.sw-experience-studio-canvas__world').attributes('style')).toBe(fittedTransform);
    });

    it('smoothly resets zoom to 100% from the existing action', async () => {
        wrapper = await createWrapper([{ id: 'layout', x: 0, y: 0, width: 1480, height: 900 }]);
        await wrapper.get(`button[aria-label="${resetLabel}"]`).trigger('click');

        expect(wrapper.get('.sw-experience-studio-canvas__zoom-controls > span').text()).toBe('100%');
        expect(wrapper.get('.sw-experience-studio-canvas__world').classes()).toContain('is--viewport-transitioning');
    });

    it('disables zoom to fit when there are no open layouts', async () => {
        wrapper = await createWrapper();

        expect(wrapper.get(`button[aria-label="${fitLabel}"]`).attributes('disabled')).toBeDefined();
    });
});
