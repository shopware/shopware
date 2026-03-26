import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Shopware } from 'shopware';

import 'shopware';
const { default: FilterSorting } = await import('./Sorting');

function buildEl(options = []) {
    const el = document.createElement('select');
    options.forEach(({ value, label }) => {
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = label;
        el.appendChild(opt);
    });
    return el;
}

describe('FilterSorting', () => {
    let el;
    let sorting;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl([
            { value: 'name-asc', label: 'Name A–Z' },
            { value: 'name-desc', label: 'Name Z–A' },
            { value: 'price-asc', label: 'Price low–high' },
        ]);
        sorting = new FilterSorting(el, {});
        sorting.init();
    });

    it('emits FilterSorting:Change with the selected value on change', () => {
        el.value = 'price-asc';
        el.dispatchEvent(new Event('change', { bubbles: true }));

        expect(Shopware.emit).toHaveBeenCalledWith('FilterSorting:Change', 'price-asc');
    });

    it('dispatches a FilterSorting:Change custom event on the element', () => {
        const handler = vi.fn();
        el.addEventListener('FilterSorting:Change', handler);

        el.value = 'name-desc';
        el.dispatchEvent(new Event('change', { bubbles: true }));

        expect(handler).toHaveBeenCalled();
        expect(handler.mock.calls[0][0].detail).toEqual({ sorting: 'name-desc' });
    });

    it('emits the correct value each time the selection changes', () => {
        el.value = 'name-asc';
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.value = 'price-asc';
        el.dispatchEvent(new Event('change', { bubbles: true }));

        expect(Shopware.emit).toHaveBeenNthCalledWith(1, 'FilterSorting:Change', 'name-asc');
        expect(Shopware.emit).toHaveBeenNthCalledWith(2, 'FilterSorting:Change', 'price-asc');
    });
});
