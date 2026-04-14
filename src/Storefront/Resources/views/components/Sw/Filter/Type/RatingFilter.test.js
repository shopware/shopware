import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Shopware } from 'shopware';

import 'shopware';
const { default: RatingFilter } = await import('./RatingFilter');

function buildEl({ checkedValue = null } = {}) {
    const el = document.createElement('div');
    el.innerHTML = [1, 2, 3, 4, 5].map(n => `
        <input type="radio" name="rating" value="${n}"${checkedValue === String(n) ? ' checked' : ''}>
    `).join('');
    return el;
}

describe('RatingFilter', () => {
    let el;
    let filter;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl();
        filter = new RatingFilter(el, {});
        filter.init();
    });

    describe('handleChange', () => {
        it('emits Filter:Change with the selected rating value', () => {
            const input = el.querySelector('input[value="3"]');
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Change', expect.objectContaining({
                paramName: 'rating',
                value: '3',
            }));
        });

        it('emits RatingFilter:Change', () => {
            const input = el.querySelector('input[value="4"]');
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('RatingFilter:Change', expect.objectContaining({
                value: '4',
            }));
        });

        it('includes a formatted label in the change event', () => {
            const input = el.querySelector('input[value="2"]');
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Change', expect.objectContaining({
                label: 'Rating 2/5',
            }));
        });

        it('dispatches a Filter:UpdateBadge custom event with the rating value', () => {
            const handler = vi.fn();
            el.addEventListener('Filter:UpdateBadge', handler);

            const input = el.querySelector('input[value="3"]');
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));

            expect(handler).toHaveBeenCalled();
            expect(handler.mock.calls[0][0].detail.content).toBe('3/5');
        });

        it('uses intercepted values when emitting', () => {
            vi.mocked(Shopware.emitInterception).mockReturnValueOnce({
                paramName: 'rating',
                value: '5',
                label: 'Top Rating',
            });

            const input = el.querySelector('input[value="1"]');
            input.checked = true;
            input.dispatchEvent(new Event('change', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Change', expect.objectContaining({
                label: 'Top Rating',
                value: '5',
            }));
        });
    });

    describe('handleFilterRemove', () => {
        it('unchecks all radio inputs when matching paramName is removed', () => {
            const input = el.querySelector('input[value="3"]');
            input.checked = true;

            filter.handleFilterRemove({ paramName: 'rating' });

            const inputs = el.querySelectorAll('input');
            inputs.forEach(i => expect(i.checked).toBe(false));
        });

        it('dispatches a Filter:UpdateBadge with empty content on removal', () => {
            const handler = vi.fn();
            el.addEventListener('Filter:UpdateBadge', handler);

            filter.handleFilterRemove({ paramName: 'rating' });

            expect(handler.mock.calls[0][0].detail.content).toBe('');
        });

        it('does nothing when a different paramName is removed', () => {
            const input = el.querySelector('input[value="3"]');
            input.checked = true;

            filter.handleFilterRemove({ paramName: 'other' });

            expect(input.checked).toBe(true);
        });
    });

    describe('reset', () => {
        it('unchecks all radio inputs', () => {
            el.querySelector('input[value="4"]').checked = true;
            filter.reset();

            const inputs = el.querySelectorAll('input');
            inputs.forEach(i => expect(i.checked).toBe(false));
        });
    });

    describe('getLabel', () => {
        it('returns a formatted label for a positive rating', () => {
            expect(filter.getLabel(3)).toBe('Rating 3/5');
        });

        it('returns empty string for a zero rating', () => {
            expect(filter.getLabel(0)).toBe('');
        });

        it('uses a custom displayName option', () => {
            const el2 = buildEl();
            const f2 = new RatingFilter(el2, { displayName: 'Stars' });
            f2.init();

            expect(f2.getLabel(4)).toBe('Stars 4/5');
        });
    });

    describe('updateBadge', () => {
        it('dispatches Filter:UpdateBadge with formatted content', () => {
            const handler = vi.fn();
            el.addEventListener('Filter:UpdateBadge', handler);

            filter.updateBadge(2, 5);

            expect(handler.mock.calls[0][0].detail.content).toBe('2/5');
        });

        it('dispatches Filter:UpdateBadge with empty content when value is 0', () => {
            const handler = vi.fn();
            el.addEventListener('Filter:UpdateBadge', handler);

            filter.updateBadge(0, 5);

            expect(handler.mock.calls[0][0].detail.content).toBe('');
        });
    });
});
