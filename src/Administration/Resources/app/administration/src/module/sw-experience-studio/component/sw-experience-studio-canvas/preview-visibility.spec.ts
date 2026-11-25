import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import type { Component } from 'vue';

const previewMounted = jest.fn();
const previewUnmounted = jest.fn();
const preview = defineComponent({
    props: { layoutId: { type: String, required: true } },
    mounted() {
        previewMounted(this.layoutId);
    },
    unmounted() {
        previewUnmounted(this.layoutId);
    },
    template: '<iframe :title="layoutId" src="about:blank"></iframe>',
});

async function createWrapper() {
    const frames = [
        { layoutId: 'first', x: 0, y: 0, width: 500, height: 400, viewport: 'desktop', previewEntityId: null },
        { layoutId: 'second', x: 1600, y: 0, width: 500, height: 400, viewport: 'desktop', previewEntityId: null },
        { layoutId: 'third', x: 3200, y: 0, width: 500, height: 400, viewport: 'desktop', previewEntityId: null },
    ];
    const wrapper = mount((await wrapTestComponent('sw-experience-studio-canvas', { sync: true })) as Component, {
        props: {
            frames: frames.map((frame) => ({
                id: frame.layoutId,
                layout: { id: frame.layoutId, name: frame.layoutId },
                selectedElementId: null,
                viewport: 'desktop',
                previewEntityId: null,
                isActive: false,
            })),
            workspaceConfiguration: {
                frames,
                previewSalesChannelId: null,
                canvas: { panX: 0, panY: 0, zoom: 0.75 },
            },
        },
        slots: {
            preview: ({ frame }: { frame: { id: string } }) => h(preview, { layoutId: frame.id }),
        },
    });
    await flushPromises();
    const stage = wrapper.get('.sw-experience-studio-canvas__stage').element;
    Object.defineProperties(stage, {
        clientWidth: { value: 1000 },
        clientHeight: { value: 800 },
    });
    jest.spyOn(stage, 'getBoundingClientRect').mockReturnValue({ width: 1000, height: 800 } as DOMRect);
    await wrapper.setData({ canvasZoom: 1 });
    await flushPromises();
    previewMounted.mockClear();
    previewUnmounted.mockClear();

    return wrapper;
}

async function movePointer(clientX: number) {
    const event = new MouseEvent('pointermove', { bubbles: true, clientX, clientY: 0 });
    Object.defineProperty(event, 'pointerId', { value: 1 });
    document.dispatchEvent(event);
    await flushPromises();
}

describe('Experience Studio preview visibility during canvas dragging', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    async function startDrag() {
        await wrapper.get('.sw-experience-studio-canvas__background').trigger('pointerdown', {
            button: 0,
            pointerId: 1,
            clientX: 0,
            clientY: 0,
        });
    }

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    it('keeps admin frames and their controls mounted while only visible previews are loaded', async () => {
        wrapper = await createWrapper();
        const frames = wrapper.findAll('[data-frame-id]');
        expect(frames).toHaveLength(3);
        expect(wrapper.findAll('iframe')).toHaveLength(1);
        expect(wrapper.find('iframe[title="first"]').exists()).toBe(true);

        for (const frame of frames) {
            expect(frame.get('.sw-experience-studio-canvas__frame-toolbar').text()).toContain(
                frame.attributes('data-frame-id'),
            );
            expect(frame.find('.sw-experience-studio-canvas__close-frame').exists()).toBe(true);
            expect(frame.findAll('.sw-experience-studio-canvas__resize-handle')).toHaveLength(8);
        }

        await wrapper.setData({ canvasPanX: -1600 });
        await flushPromises();

        expect(wrapper.findAll('[data-frame-id]').map((frame) => frame.element)).toEqual(
            frames.map((frame) => frame.element),
        );
        expect(wrapper.findAll('iframe')).toHaveLength(1);
        expect(wrapper.find('iframe[title="first"]').exists()).toBe(false);
        expect(wrapper.find('iframe[title="second"]').exists()).toBe(true);
        expect(previewMounted.mock.calls).toEqual([['second']]);
        expect(previewUnmounted.mock.calls).toEqual([['first']]);
    });

    it('keeps previews mounted throughout rapid panning and updates visibility once on release', async () => {
        wrapper = await createWrapper();
        const originalPreview = wrapper.get('iframe[title="first"]').element;
        expect(wrapper.findAll('[data-frame-id]')).toHaveLength(3);
        await startDrag();

        for (const clientX of [
            -1600,
            -3200,
            0,
            -3200,
            1600,
            -1600,
        ]) {
            await movePointer(clientX);

            expect(wrapper.get('.sw-experience-studio-canvas__world').attributes('style')).toContain(
                `translate3d(${clientX}px, 0px, 0)`,
            );
            expect(wrapper.findAll('[data-frame-id]')).toHaveLength(3);
            expect(wrapper.get('iframe').element).toBe(originalPreview);
            expect(previewMounted).not.toHaveBeenCalled();
            expect(previewUnmounted).not.toHaveBeenCalled();
        }

        document.dispatchEvent(new Event('pointerup'));
        await flushPromises();

        expect(wrapper.find('iframe[title="first"]').exists()).toBe(false);
        expect(wrapper.find('iframe[title="second"]').exists()).toBe(true);
        expect(wrapper.find('iframe[title="third"]').exists()).toBe(false);
        expect(previewMounted.mock.calls).toEqual([['second']]);
        expect(previewUnmounted.mock.calls).toEqual([['first']]);
    });

    it.each(['pointercancel', 'blur'])(
        'refreshes visibility after %s and takes a fresh snapshot on the next drag',
        async (end) => {
            wrapper = await createWrapper();
            await startDrag();
            await movePointer(-1600);
            expect(wrapper.find('iframe[title="first"]').exists()).toBe(true);
            expect(wrapper.find('iframe[title="second"]').exists()).toBe(false);

            (end === 'blur' ? window : document).dispatchEvent(new Event(end));
            await flushPromises();
            const secondPreview = wrapper.get('iframe[title="second"]').element;
            expect(wrapper.find('iframe[title="first"]').exists()).toBe(false);
            previewMounted.mockClear();
            previewUnmounted.mockClear();

            await startDrag();
            await movePointer(1600);
            expect(wrapper.get('iframe').element).toBe(secondPreview);
            expect(previewMounted).not.toHaveBeenCalled();
            expect(previewUnmounted).not.toHaveBeenCalled();

            document.dispatchEvent(new Event('pointerup'));
            await flushPromises();
            expect(wrapper.find('iframe[title="first"]').exists()).toBe(true);
            expect(wrapper.find('iframe[title="second"]').exists()).toBe(false);
            expect(previewMounted.mock.calls).toEqual([['first']]);
            expect(previewUnmounted.mock.calls).toEqual([['second']]);
        },
    );
});
