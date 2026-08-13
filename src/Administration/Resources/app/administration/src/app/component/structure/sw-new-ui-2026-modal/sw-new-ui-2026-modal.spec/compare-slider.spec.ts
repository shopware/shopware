/**
 * @sw-package framework
 */

import { type VueWrapper } from '@vue/test-utils';
import createWrapper, { setIntendedAudience } from './create-wrapper';

// Mocked on the prototype so it covers whichever compare element the current render owns.
function mockCompareBounds(left: number, width: number) {
    jest.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({ left, width } as DOMRect);
}

function compareElement(currentWrapper: VueWrapper): HTMLElement {
    return currentWrapper.get('.sw-new-ui-2026-modal__compare').element as HTMLElement;
}

function splitOf(currentWrapper: VueWrapper): string {
    return compareElement(currentWrapper).style.getPropertyValue('--sw-new-ui-2026-modal-split');
}

/** The drag follows the pointer on window, so moves and releases are dispatched there. */
function dispatchPointer(type: 'pointermove' | 'pointerup' | 'pointercancel', clientX = 0) {
    window.dispatchEvent(new MouseEvent(type, { clientX }));
}

/**
 * The move that takes the drag over from the press hands its position through a render, so
 * that the transition is dropped in the same frame. Later moves are written synchronously.
 */
async function takeOverDrag(clientX: number) {
    dispatchPointer('pointermove', clientX);
    await flushPromises();
}

describe('src/app/component/structure/sw-new-ui-2026-modal - before/after slider', () => {
    let wrapper: VueWrapper | null = null;

    beforeEach(() => {
        setIntendedAudience();
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }

        jest.useRealTimers();
    });

    it('leaves the split to the stylesheet default until it is dragged', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(splitOf(wrapper)).toBe('');
    });

    it('renders a handle on the seam', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-new-ui-2026-modal__compare-handle').exists()).toBe(true);
    });

    it('does not move while the pointer only hovers the images', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointermove', { clientX: 300 });
        dispatchPointer('pointermove', 300);
        await flushPromises();

        expect(splitOf(wrapper)).toBe('');
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--settled',
        );
    });

    it('does not move the reveal on a press alone, so a click leaves it alone', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });
        dispatchPointer('pointerup', 200);
        await flushPromises();

        expect(splitOf(wrapper)).toBe('');
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--settled',
        );
    });

    it('follows the pointer once the drag has started', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });

        await takeOverDrag(400);

        expect(splitOf(wrapper)).toBe('75%');

        dispatchPointer('pointermove', 300);

        expect(splitOf(wrapper)).toBe('50%');
    });

    it('keeps following a pointer dragged outside the images', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });

        await takeOverDrag(900);

        expect(splitOf(wrapper)).toBe('100%');

        dispatchPointer('pointermove', -50);

        expect(splitOf(wrapper)).toBe('0%');
    });

    it('stops following once the pointer is released', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });
        await takeOverDrag(400);
        dispatchPointer('pointerup', 400);
        await flushPromises();

        dispatchPointer('pointermove', 140);

        expect(splitOf(wrapper)).toBe('75%');
    });

    it('stops following when the drag is cancelled', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });
        await takeOverDrag(400);
        dispatchPointer('pointercancel');
        await flushPromises();

        dispatchPointer('pointermove', 140);

        expect(splitOf(wrapper)).toBe('75%');
    });

    it('keeps the position it was released at instead of re-centering', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);
        jest.useFakeTimers();

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });
        await takeOverDrag(400);
        dispatchPointer('pointerup', 400);
        await flushPromises();

        jest.advanceTimersByTime(10000);
        await flushPromises();

        expect(splitOf(wrapper)).toBe('75%');
    });

    it('marks the drag while the pointer is down', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });

        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).toContain('sw-new-ui-2026-modal__compare--dragging');

        dispatchPointer('pointerup', 200);
        await flushPromises();

        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--dragging',
        );
    });

    it('snaps the split to whole pixels so the seam cannot bleed', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });

        // Past the threshold first, so the sub-pixel moves below are taken as drag moves.
        await takeOverDrag(400);

        dispatchPointer('pointermove', 200.4);

        expect(splitOf(wrapper)).toBe('25%');

        dispatchPointer('pointermove', 200.6);

        expect(splitOf(wrapper)).toBe('25.25%');
    });

    // Dragging has to feel immediate, so no transition may ever be in effect while it runs.
    it('never eases while dragging', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });

        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--eased',
        );

        await takeOverDrag(400);

        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--eased',
        );
    });

    // Only the move that takes over goes through a render, so stopping the idle hint lands in
    // the same frame as the position. An inline write there would lose to the animation.
    it('hands the first drag move through a render and writes the rest directly', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });

        dispatchPointer('pointermove', 400);

        expect(splitOf(wrapper)).toBe('');

        await flushPromises();

        expect(splitOf(wrapper)).toBe('75%');

        // Already taken over, so this one is on the element before the next render.
        dispatchPointer('pointermove', 300);

        expect(splitOf(wrapper)).toBe('50%');
    });

    it('ignores the jitter a click carries instead of taking it for a drag', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });

        // Two pixels is a shaky hand, not a drag.
        dispatchPointer('pointermove', 202);
        await flushPromises();

        expect(splitOf(wrapper)).toBe('');

        // Past the threshold it becomes one, and picks up the position from there.
        await takeOverDrag(204);

        expect(splitOf(wrapper)).toBe('26%');
    });

    it('ignores the drag while the images have no width', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(0, 0);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });
        await takeOverDrag(400);

        expect(splitOf(wrapper)).toBe('');
    });

    it('stops the idle hint by class rather than by an inline animation-name', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--settled',
        );

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });
        await takeOverDrag(400);

        // An inline animation-name could never be handed back, so the drag has to commit the
        // position and let --settled silence the hint.
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).toContain('sw-new-ui-2026-modal__compare--settled');
        expect(compareElement(wrapper).style.animationName).toBe('');
    });

    it('slides the reveal fully over to the new navigation on the dark mode page', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        const compare = wrapper.get('.sw-new-ui-2026-modal__compare');

        expect(splitOf(wrapper)).toBe('100%');
        expect(compare.classes()).toContain('sw-new-ui-2026-modal__compare--eased');
        expect(compare.classes()).toContain('sw-new-ui-2026-modal__compare--pinned');
    });

    it('hides the handle and ignores the pointer while the reveal is pinned', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-new-ui-2026-modal__compare-handle').exists()).toBe(false);

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });
        dispatchPointer('pointermove', 200);

        expect(splitOf(wrapper)).toBe('100%');
    });

    it('eases the reveal back to the middle when returning to the first page', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        jest.useFakeTimers();

        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        expect(splitOf(wrapper)).toBe('100%');

        await wrapper.get('.sw-new-ui-2026-modal__footer-left button').trigger('click');
        await flushPromises();

        const compare = wrapper.get('.sw-new-ui-2026-modal__compare');

        expect(splitOf(wrapper)).toBe('50%');
        expect(compare.classes()).toContain('sw-new-ui-2026-modal__compare--eased');
        expect(compare.classes()).not.toContain('sw-new-ui-2026-modal__compare--pinned');
    });

    it('re-centers a dragged position only when paging back from the pinned page', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);
        jest.useFakeTimers();

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 500 });
        await takeOverDrag(200);
        dispatchPointer('pointerup', 200);
        await flushPromises();

        expect(splitOf(wrapper)).toBe('25%');

        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-new-ui-2026-modal__footer-left button').trigger('click');
        await flushPromises();

        expect(splitOf(wrapper)).toBe('50%');
    });

    it('hands the reveal back to the idle hint once it has slid back', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        jest.useFakeTimers();

        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-new-ui-2026-modal__footer-left button').trigger('click');
        await flushPromises();

        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).toContain('sw-new-ui-2026-modal__compare--settled');

        jest.advanceTimersByTime(300);
        await flushPromises();

        // Handed over at the same position the transition ended on, so nothing jumps.
        expect(splitOf(wrapper)).toBe('');
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--settled',
        );
        expect(wrapper.get('.sw-new-ui-2026-modal__compare').classes()).not.toContain(
            'sw-new-ui-2026-modal__compare--eased',
        );
    });

    it('keeps a position taken over mid-slide instead of handing it to the hint', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);
        jest.useFakeTimers();

        await wrapper.get('.sw-new-ui-2026-modal__footer-right button').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-new-ui-2026-modal__footer-left button').trigger('click');
        await flushPromises();

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 500 });
        await takeOverDrag(200);
        dispatchPointer('pointerup', 200);

        expect(splitOf(wrapper)).toBe('25%');

        jest.advanceTimersByTime(300);
        await flushPromises();

        expect(splitOf(wrapper)).toBe('25%');
    });

    it('stops listening for the drag when the modal goes away mid-gesture', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        const removeEventListener = jest.spyOn(window, 'removeEventListener');

        await wrapper.get('.sw-new-ui-2026-modal__compare').trigger('pointerdown', { clientX: 200 });

        wrapper.unmount();
        wrapper = null;

        expect(removeEventListener).toHaveBeenCalledWith('pointermove', expect.any(Function));
        expect(removeEventListener).toHaveBeenCalledWith('pointerup', expect.any(Function));
        expect(removeEventListener).toHaveBeenCalledWith('pointercancel', expect.any(Function));
    });
});
