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
    return currentWrapper.get('.sw-whats-new-modal__compare').element as HTMLElement;
}

function splitOf(currentWrapper: VueWrapper): string {
    return compareElement(currentWrapper).style.getPropertyValue('--sw-whats-new-modal-split');
}

describe('src/app/component/structure/sw-whats-new-modal - before/after slider', () => {
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

    it('leaves the split to the stylesheet default until the mouse moves', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(splitOf(wrapper)).toBe('');
    });

    it('follows the mouse position across the images', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 200 });

        expect(splitOf(wrapper)).toBe('25%');

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 400 });

        expect(splitOf(wrapper)).toBe('75%');
    });

    it('snaps the split to whole pixels so the seam cannot bleed', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 200.4 });

        expect(splitOf(wrapper)).toBe('25%');

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 200.6 });

        expect(splitOf(wrapper)).toBe('25.25%');
    });

    it('clamps the split to the bounds of the images', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 20 });

        expect(splitOf(wrapper)).toBe('0%');

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 900 });

        expect(splitOf(wrapper)).toBe('100%');
    });

    it('ignores the mouse while the images have no width', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(0, 0);

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 200 });

        expect(splitOf(wrapper)).toBe('');
    });

    it('keeps the reveal on the side the mouse left through', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mouseleave', { clientX: 60 });

        expect(splitOf(wrapper)).toBe('0%');

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mouseleave', { clientX: 560 });

        expect(splitOf(wrapper)).toBe('100%');
    });

    it('holds its position when the mouse leaves over the top or bottom edge', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 400 });

        expect(splitOf(wrapper)).toBe('75%');

        // Leaving upwards keeps the horizontal position instead of snapping to an edge.
        await wrapper.get('.sw-whats-new-modal__compare').trigger('mouseleave', { clientX: 400 });

        expect(splitOf(wrapper)).toBe('75%');
    });

    it('stops the idle hint by class rather than by an inline animation-name', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        expect(wrapper.get('.sw-whats-new-modal__compare').classes()).not.toContain('sw-whats-new-modal__compare--settled');

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 200 });

        // An inline animation-name could never be handed back, so the very first move has to
        // commit the position and let --settled silence the hint.
        expect(wrapper.get('.sw-whats-new-modal__compare').classes()).toContain('sw-whats-new-modal__compare--settled');
        expect(compareElement(wrapper).style.animationName).toBe('');
    });

    it('eases back to the middle once the mouse goes idle', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);
        jest.useFakeTimers();

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 400 });

        expect(splitOf(wrapper)).toBe('75%');

        jest.advanceTimersByTime(2999);

        expect(splitOf(wrapper)).toBe('75%');

        jest.advanceTimersByTime(1);
        await flushPromises();

        expect(splitOf(wrapper)).toBe('50%');
        expect(wrapper.get('.sw-whats-new-modal__compare').classes()).toContain('sw-whats-new-modal__compare--eased');
    });

    it('drops the recenter easing again as soon as the mouse moves', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);
        jest.useFakeTimers();

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 400 });
        jest.advanceTimersByTime(3000);
        await flushPromises();

        expect(wrapper.get('.sw-whats-new-modal__compare').classes()).toContain('sw-whats-new-modal__compare--eased');

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 300 });
        await flushPromises();

        expect(wrapper.get('.sw-whats-new-modal__compare').classes()).not.toContain('sw-whats-new-modal__compare--eased');
    });

    it('restarts the idle countdown on every move', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);
        jest.useFakeTimers();

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 400 });
        jest.advanceTimersByTime(2000);
        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 200 });
        jest.advanceTimersByTime(2000);

        // 4s since the first move, but only 2s since the last one.
        expect(splitOf(wrapper)).toBe('25%');

        jest.advanceTimersByTime(1000);
        await flushPromises();

        expect(splitOf(wrapper)).toBe('50%');
    });

    it('slides the reveal fully over to the new navigation on the dark mode page', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-whats-new-modal__footer-right button').trigger('click');
        await flushPromises();

        const compare = wrapper.get('.sw-whats-new-modal__compare');

        expect(splitOf(wrapper)).toBe('100%');
        expect(compare.classes()).toContain('sw-whats-new-modal__compare--eased');
        expect(compare.classes()).toContain('sw-whats-new-modal__compare--pinned');
    });

    it('ignores the mouse while the reveal is pinned', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);

        await wrapper.get('.sw-whats-new-modal__footer-right button').trigger('click');
        await flushPromises();

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 200 });
        await wrapper.get('.sw-whats-new-modal__compare').trigger('mouseleave', { clientX: 20 });

        expect(splitOf(wrapper)).toBe('100%');
    });

    it('eases the reveal back to the middle when returning to the first page', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        jest.useFakeTimers();

        await wrapper.get('.sw-whats-new-modal__footer-right button').trigger('click');
        await flushPromises();

        expect(splitOf(wrapper)).toBe('100%');

        await wrapper.get('.sw-whats-new-modal__footer-left button').trigger('click');
        await flushPromises();

        const compare = wrapper.get('.sw-whats-new-modal__compare');

        expect(splitOf(wrapper)).toBe('50%');
        expect(compare.classes()).toContain('sw-whats-new-modal__compare--eased');
        expect(compare.classes()).not.toContain('sw-whats-new-modal__compare--pinned');
    });

    it('hands the reveal back to the idle hint once it has slid back', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        jest.useFakeTimers();

        await wrapper.get('.sw-whats-new-modal__footer-right button').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-whats-new-modal__footer-left button').trigger('click');
        await flushPromises();

        expect(wrapper.get('.sw-whats-new-modal__compare').classes()).toContain('sw-whats-new-modal__compare--settled');

        jest.advanceTimersByTime(300);
        await flushPromises();

        // Handed over at the same position the transition ended on, so nothing jumps.
        expect(splitOf(wrapper)).toBe('');
        expect(wrapper.get('.sw-whats-new-modal__compare').classes()).not.toContain('sw-whats-new-modal__compare--settled');
        expect(wrapper.get('.sw-whats-new-modal__compare').classes()).not.toContain('sw-whats-new-modal__compare--eased');
    });

    it('keeps a position taken over mid-slide instead of handing it to the hint', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);
        jest.useFakeTimers();

        await wrapper.get('.sw-whats-new-modal__footer-right button').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-whats-new-modal__footer-left button').trigger('click');
        await flushPromises();

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 200 });

        expect(splitOf(wrapper)).toBe('25%');

        jest.advanceTimersByTime(300);
        await flushPromises();

        expect(splitOf(wrapper)).toBe('25%');
    });

    it('drops a pending recenter when the page changes', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        mockCompareBounds(100, 400);
        jest.useFakeTimers();

        const pending = () => (wrapper?.vm as unknown as { recenterTimeout: number | null }).recenterTimeout;

        await wrapper.get('.sw-whats-new-modal__compare').trigger('mousemove', { clientX: 400 });

        expect(pending()).not.toBeNull();

        await wrapper.get('.sw-whats-new-modal__footer-right button').trigger('click');
        await flushPromises();

        expect(pending()).toBeNull();
    });
});
