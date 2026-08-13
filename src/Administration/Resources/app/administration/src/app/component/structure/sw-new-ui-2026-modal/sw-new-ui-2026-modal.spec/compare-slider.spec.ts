/**
 * @sw-package framework
 */

import { type VueWrapper, type DOMWrapper } from '@vue/test-utils';
import createWrapper, { setIntendedAudience } from './create-wrapper';

// Only the width matters: the drag moves the seam by deltas, never to an absolute edge.
function mockCompareWidth(width: number) {
    jest.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({ width } as DOMRect);
}

describe('src/app/component/structure/sw-new-ui-2026-modal - before/after slider', () => {
    let wrapper: VueWrapper;

    function compareElement(): HTMLElement {
        return wrapper.get('.sw-new-ui-2026-modal__compare').element as HTMLElement;
    }

    function splitOf(): string {
        return compareElement().style.getPropertyValue('--sw-new-ui-2026-modal-split');
    }

    function handle(): DOMWrapper<Element> {
        return wrapper.get('.sw-new-ui-2026-modal__compare-handle');
    }

    async function pressHandle(clientX: number, options: Record<string, unknown> = {}) {
        await handle().trigger('pointerdown', { clientX, button: 0, ...options });
    }

    async function moveHandle(clientX: number, options: Record<string, unknown> = {}) {
        await handle().trigger('pointermove', { clientX, ...options });
    }

    async function releaseHandle(clientX: number, options: Record<string, unknown> = {}) {
        await handle().trigger('pointerup', { clientX, ...options });
    }

    beforeEach(async () => {
        setIntendedAudience();

        wrapper = await createWrapper();
        await flushPromises();

        mockCompareWidth(400);
    });

    afterEach(() => {
        wrapper.unmount();

        jest.useRealTimers();
    });

    it('leaves the split to the stylesheet default until the handle is pressed', () => {
        expect(splitOf()).toBe('');
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--settled',
        );
    });

    it('renders a handle on the seam', () => {
        expect(handle().exists()).toBe(true);
    });

    it('does not move while the pointer only hovers the images or the handle', async () => {
        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointermove', { clientX: 300 });
        await moveHandle(300);

        expect(splitOf()).toBe('');
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--settled',
        );
    });

    it('ignores presses on the images, so only the handle drags the seam', async () => {
        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 300, button: 0 });
        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointermove', { clientX: 400 });

        expect(splitOf()).toBe('');
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--dragging',
        );
    });

    it('settles the seam where it stands on a press without moving it', async () => {
        await pressHandle(500);
        await releaseHandle(500);

        expect(splitOf()).toBe('50%');
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).toContain('sw-new-ui-2026-modal__compare--settled');
    });

    it('moves the seam by the dragged distance instead of jumping to the pointer', async () => {
        await pressHandle(500);

        // 100px on 400px width is a quarter of the reveal.
        await moveHandle(600);

        expect(splitOf()).toBe('75%');

        await moveHandle(400);

        expect(splitOf()).toBe('25%');
    });

    it('clamps the drag to the edges of the images', async () => {
        await pressHandle(500);

        await moveHandle(2000);

        expect(splitOf()).toBe('100%');

        await moveHandle(-2000);

        expect(splitOf()).toBe('0%');
    });

    it('stops following once the pointer is released', async () => {
        await pressHandle(500);
        await moveHandle(600);
        await releaseHandle(600);

        await moveHandle(200);

        expect(splitOf()).toBe('75%');
    });

    it('stops following when the drag is cancelled', async () => {
        await pressHandle(500);
        await moveHandle(600);
        await handle().trigger('pointercancel');

        await moveHandle(200);

        expect(splitOf()).toBe('75%');
    });

    it('keeps the position it was released at instead of re-centering', async () => {
        jest.useFakeTimers();

        await pressHandle(500);
        await moveHandle(600);
        await releaseHandle(600);

        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(splitOf()).toBe('75%');
    });

    it('marks the drag while the pointer is down', async () => {
        await pressHandle(500);

        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).toContain('sw-new-ui-2026-modal__compare--dragging');

        await releaseHandle(500);

        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--dragging',
        );
    });

    it('ignores presses of any button but the primary one', async () => {
        await handle().trigger('pointerdown', { clientX: 500, button: 2 });
        await moveHandle(600);

        expect(splitOf()).toBe('');
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--dragging',
        );
    });

    it('ignores every pointer but the one that started the drag', async () => {
        await pressHandle(500, { pointerId: 1 });
        await moveHandle(600, { pointerId: 1 });

        expect(splitOf()).toBe('75%');

        // A second finger must neither move the seam, restart the drag, nor end it.
        await moveHandle(200, { pointerId: 2 });
        await pressHandle(200, { pointerId: 2 });
        await releaseHandle(200, { pointerId: 2 });

        expect(splitOf()).toBe('75%');

        await moveHandle(500, { pointerId: 1 });

        expect(splitOf()).toBe('50%');

        await releaseHandle(500, { pointerId: 1 });

        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--dragging',
        );
    });

    it('snaps the split to whole pixels so the seam cannot bleed', async () => {
        await pressHandle(500);

        await moveHandle(500.4);

        expect(splitOf()).toBe('50%');

        await moveHandle(500.6);

        expect(splitOf()).toBe('50.25%');
    });

    it('never eases while dragging', async () => {
        await pressHandle(500);

        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--eased',
        );

        await moveHandle(600);

        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--eased',
        );
    });

    it('commits the press through a render and writes the moves directly', async () => {
        await pressHandle(500);

        expect(splitOf()).toBe('50%');

        handle().element.dispatchEvent(new MouseEvent('pointermove', { clientX: 600 }));

        expect(splitOf()).toBe('75%');
    });

    it('ignores the drag while the images have no width', async () => {
        mockCompareWidth(0);

        await pressHandle(500);
        await moveHandle(600);

        expect(splitOf()).toBe('');
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--dragging',
        );
    });

    it('stops the idle hint by class rather than by an inline animation-name', async () => {
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--settled',
        );

        await pressHandle(500);
        await moveHandle(600);

        // An inline animation-name could never be handed back, so the drag commits the
        // position and lets --settled silence the hint.
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).toContain('sw-new-ui-2026-modal__compare--settled');
        expect(compareElement().style.animationName).toBe('');
    });

    it('slides the reveal fully over to the new navigation on the dark mode page', async () => {
        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        const compare = wrapper.get('.sw-new-ui-2026-modal__compare');

        expect(splitOf()).toBe('100%');
        expect(compare.classes()).toContain('sw-new-ui-2026-modal__compare--eased');
        expect(compare.classes()).toContain('sw-new-ui-2026-modal__compare--pinned');
    });

    it('offers no handle while the reveal is pinned', async () => {
        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-new-ui-2026-modal__compare-handle').exists()).toBe(false);
    });

    it('cancels a drag that survives paging instead of overwriting the pinned split', async () => {
        await pressHandle(500);
        await moveHandle(400);

        expect(splitOf()).toBe('25%');

        // A captured pointer keeps sending its events to the handle even once the page
        // switch has taken it out of the DOM.
        const detachedHandle = handle().element;

        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        detachedHandle.dispatchEvent(new MouseEvent('pointermove', { clientX: 300 }));
        detachedHandle.dispatchEvent(new MouseEvent('pointerup', { clientX: 300 }));
        await flushPromises();

        expect(splitOf()).toBe('100%');
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--dragging',
        );
    });

    it('resets the drag when the modal closes mid-gesture', async () => {
        await pressHandle(500);
        await moveHandle(600);

        await wrapper.get('.mt-modal__close-button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
        expect((wrapper.vm as unknown as { drag: unknown }).drag).toBeNull();
    });

    it('eases the reveal back to the middle when returning to the first page', async () => {
        jest.useFakeTimers();

        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        expect(splitOf()).toBe('100%');

        await wrapper.get('.sw-new-ui-2026-modal__footer-left button').trigger('click');
        await flushPromises();

        const compare = wrapper.get('.sw-new-ui-2026-modal__compare');

        expect(splitOf()).toBe('50%');
        expect(compare.classes()).toContain('sw-new-ui-2026-modal__compare--eased');
        expect(compare.classes()).not.toContain('sw-new-ui-2026-modal__compare--pinned');
    });

    it('re-centers a dragged position only when paging back from the pinned page', async () => {
        jest.useFakeTimers();

        await pressHandle(500);
        await moveHandle(400);
        await releaseHandle(400);

        expect(splitOf()).toBe('25%');

        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-new-ui-2026-modal__footer-left button').trigger('click');
        await flushPromises();

        expect(splitOf()).toBe('50%');
    });

    it('hands the reveal back to the idle hint once it has slid back', async () => {
        jest.useFakeTimers();

        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-new-ui-2026-modal__footer-left button').trigger('click');
        await flushPromises();

        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).toContain('sw-new-ui-2026-modal__compare--settled');

        jest.advanceTimersByTime(300);
        await flushPromises();

        // Handed over at the same position the transition ended on, so nothing jumps.
        expect(splitOf()).toBe('');
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--settled',
        );
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--eased',
        );
    });

    it('keeps a position grabbed mid-slide instead of handing it to the hint', async () => {
        jest.useFakeTimers();

        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-new-ui-2026-modal__footer-left button').trigger('click');
        await flushPromises();

        await pressHandle(500);
        await moveHandle(400);
        await releaseHandle(400);

        expect(splitOf()).toBe('25%');

        jest.advanceTimersByTime(300);
        await flushPromises();

        expect(splitOf()).toBe('25%');
    });
});
