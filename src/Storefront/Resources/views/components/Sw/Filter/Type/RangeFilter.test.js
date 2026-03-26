import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Shopware } from 'shopware';

import 'shopware';
const { default: RangeFilter } = await import('./RangeFilter');

function buildEl({ minValue = '', maxValue = '' } = {}) {
    const el = document.createElement('div');
    el.innerHTML = `
        <input class="sw-range-filter__min-input" name="price-min" type="number" value="${minValue}">
        <input class="sw-range-filter__max-input" name="price-max" type="number" value="${maxValue}">
    `;
    return el;
}

describe('RangeFilter', () => {
    let el;
    let filter;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl();
        filter = new RangeFilter(el, {});
        filter.init();
    });

    describe('handleRangeInput', () => {
        it('emits Filter:Change with the min param when min input changes', () => {
            const minInput = el.querySelector('.sw-range-filter__min-input');
            minInput.value = '10';
            minInput.dispatchEvent(new Event('input', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Change', expect.objectContaining({
                paramName: 'price-min',
                value: '10',
            }));
        });

        it('emits Filter:Change with the max param when max input changes', () => {
            const maxInput = el.querySelector('.sw-range-filter__max-input');
            maxInput.value = '100';
            maxInput.dispatchEvent(new Event('input', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Change', expect.objectContaining({
                paramName: 'price-max',
                value: '100',
            }));
        });

        it('emits RangeFilter:Change', () => {
            const minInput = el.querySelector('.sw-range-filter__min-input');
            minInput.value = '5';
            minInput.dispatchEvent(new Event('input', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('RangeFilter:Change', expect.objectContaining({
                paramName: 'price-min',
            }));
        });

        it('includes the unit from options in the change event', () => {
            const el2 = buildEl();
            const f2 = new RangeFilter(el2, { unit: '€' });
            f2.init();

            const minInput = el2.querySelector('.sw-range-filter__min-input');
            minInput.value = '20';
            minInput.dispatchEvent(new Event('input', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Change', expect.objectContaining({
                unit: '€',
            }));
        });

        it('dispatches Filter:UpdateBadge after a range change', () => {
            const handler = vi.fn();
            el.addEventListener('Filter:UpdateBadge', handler);

            const minInput = el.querySelector('.sw-range-filter__min-input');
            minInput.value = '10';
            minInput.dispatchEvent(new Event('input', { bubbles: true }));

            expect(handler).toHaveBeenCalled();
        });
    });

    describe('handleFilterRemove', () => {
        it('clears the min input when the min paramName is removed', () => {
            const minInput = el.querySelector('.sw-range-filter__min-input');
            minInput.value = '10';

            filter.handleFilterRemove({ paramName: 'price-min' });

            expect(minInput.value).toBe('');
        });

        it('clears the max input when the max paramName is removed', () => {
            const maxInput = el.querySelector('.sw-range-filter__max-input');
            maxInput.value = '100';

            filter.handleFilterRemove({ paramName: 'price-max' });

            expect(maxInput.value).toBe('');
        });

        it('dispatches Filter:UpdateBadge after removal', () => {
            const handler = vi.fn();
            el.addEventListener('Filter:UpdateBadge', handler);

            filter.handleFilterRemove({ paramName: 'price-min' });

            expect(handler).toHaveBeenCalled();
        });
    });

    describe('reset', () => {
        it('clears both min and max inputs', () => {
            const minInput = el.querySelector('.sw-range-filter__min-input');
            const maxInput = el.querySelector('.sw-range-filter__max-input');
            minInput.value = '10';
            maxInput.value = '100';

            filter.reset();

            expect(minInput.value).toBe('');
            expect(maxInput.value).toBe('');
        });
    });

    describe('updateBadge', () => {
        it('dispatches empty content when both inputs are empty', () => {
            const handler = vi.fn();
            el.addEventListener('Filter:UpdateBadge', handler);

            filter.updateBadge();

            expect(handler.mock.calls[0][0].detail.content).toBe('');
        });

        it('shows ">= min" format when only min is set', () => {
            const handler = vi.fn();
            el.addEventListener('Filter:UpdateBadge', handler);

            el.querySelector('.sw-range-filter__min-input').value = '10';
            filter.updateBadge();

            expect(handler.mock.calls[0][0].detail.content).toContain('10');
        });

        it('shows "<= max" format when only max is set', () => {
            const handler = vi.fn();
            el.addEventListener('Filter:UpdateBadge', handler);

            el.querySelector('.sw-range-filter__max-input').value = '100';
            filter.updateBadge();

            expect(handler.mock.calls[0][0].detail.content).toContain('100');
        });

        it('shows "min - max" format when both are set', () => {
            const handler = vi.fn();
            el.addEventListener('Filter:UpdateBadge', handler);

            el.querySelector('.sw-range-filter__min-input').value = '10';
            el.querySelector('.sw-range-filter__max-input').value = '100';
            filter.updateBadge();

            const content = handler.mock.calls[0][0].detail.content;
            expect(content).toContain('10');
            expect(content).toContain('100');
            expect(content).toContain(' - ');
        });
    });

    describe('getMinLabel / getMaxLabel', () => {
        it('returns a "Min: value" label for a positive min value', () => {
            expect(filter.getMinLabel(10)).toBe('Min: 10');
        });

        it('returns a "Max: value" label for a positive max value', () => {
            expect(filter.getMaxLabel(100)).toBe('Max: 100');
        });

        it('returns empty string for zero or negative values', () => {
            expect(filter.getMinLabel(0)).toBe('');
            expect(filter.getMaxLabel(0)).toBe('');
        });

        it('appends the unit option', () => {
            const el2 = buildEl();
            const f2 = new RangeFilter(el2, { unit: '€', minLabel: 'From', maxLabel: 'To' });
            f2.init();

            expect(f2.getMinLabel(50)).toBe('From: 50€');
            expect(f2.getMaxLabel(200)).toBe('To: 200€');
        });
    });
});
