import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Shopware } from 'shopware';

vi.mock('shopware');

import 'shopware';
const { default: ActiveFilters } = await import('./ActiveFilters');

function buildEl() {
    const el = document.createElement('div');
    el.innerHTML = '<button class="sw-active-filters__btn-reset-all is--hidden"></button>';
    return el;
}

describe('ActiveFilters', () => {
    let el;
    let af;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl();
        af = new ActiveFilters(el, {});
        af.init();
    });

    describe('handleFilterChange', () => {
        it('creates an active filter label when value is non-empty', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: null });

            expect(el.querySelector('[data-filter="color"]')).not.toBeNull();
        });

        it('removes an active filter label when value is empty string', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: null });
            af.handleFilterChange({ paramName: 'color', value: '', label: 'Red', option: null });

            expect(el.querySelector('[data-filter="color"]')).toBeNull();
        });

        it('removes an active filter label when value is null', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: null });
            af.handleFilterChange({ paramName: 'color', value: null, label: 'Red', option: null });

            expect(el.querySelector('[data-filter="color"]')).toBeNull();
        });

        it('shows the reset-all button when filters exist', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: null });

            expect(el.querySelector('.sw-active-filters__btn-reset-all').classList.contains('is--hidden')).toBe(false);
        });

        it('hides the reset-all button when all filters are cleared', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: null });
            af.handleFilterChange({ paramName: 'color', value: '', label: 'Red', option: null });

            expect(el.querySelector('.sw-active-filters__btn-reset-all').classList.contains('is--hidden')).toBe(true);
        });

        it('tracks options separately using paramName-option key', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: 'red' });
            af.handleFilterChange({ paramName: 'color', value: 'blue', label: 'Blue', option: 'blue' });

            expect(el.querySelectorAll('[data-filter="color"]')).toHaveLength(2);
        });

        it('updates the label text when the same filter is emitted again', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: null });
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Crimson', option: null });

            const labelEl = el.querySelector('.sw-active-filter__label');
            expect(labelEl.innerHTML).toBe('Crimson');
        });
    });

    describe('handleFilterInit', () => {
        it('creates an active filter label for a non-empty value', () => {
            af.handleFilterInit({ paramName: 'size', value: 'L', label: 'Large', option: null });

            expect(el.querySelector('[data-filter="size"]')).not.toBeNull();
        });

        it('does nothing when value is empty', () => {
            af.handleFilterInit({ paramName: 'size', value: '', label: '', option: null });

            expect(el.querySelector('[data-filter="size"]')).toBeNull();
        });

        it('does nothing when value is null', () => {
            af.handleFilterInit({ paramName: 'size', value: null, label: '', option: null });

            expect(el.querySelector('[data-filter="size"]')).toBeNull();
        });
    });

    describe('resetAll', () => {
        it('emits Filter:Remove for each active filter', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: 'red' });
            af.handleFilterChange({ paramName: 'size', value: 'L', label: 'L', option: null });
            vi.clearAllMocks();

            af.resetAll();

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Remove', { paramName: 'color', option: 'red' });
            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Remove', { paramName: 'size', option: null });
        });

        it('emits Filter:ResetAll', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: null });
            vi.clearAllMocks();

            af.resetAll();

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:ResetAll');
        });

        it('hides the reset-all button after resetting', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: null });
            af.resetAll();

            expect(el.querySelector('.sw-active-filters__btn-reset-all').classList.contains('is--hidden')).toBe(true);
        });

        it('removes all active filter labels from the DOM', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: null });
            af.handleFilterChange({ paramName: 'size', value: 'L', label: 'L', option: null });
            af.resetAll();

            expect(el.querySelectorAll('.sw-active-filter__item')).toHaveLength(0);
        });
    });

    describe('active filter click', () => {
        it('emits Filter:Remove when an active filter item is clicked', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: null });
            vi.clearAllMocks();

            el.querySelector('[data-filter="color"]').click();

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Remove', { paramName: 'color', option: null });
        });

        it('removes the clicked filter label from the DOM', () => {
            af.handleFilterChange({ paramName: 'color', value: 'red', label: 'Red', option: null });
            el.querySelector('[data-filter="color"]').click();

            expect(el.querySelector('[data-filter="color"]')).toBeNull();
        });
    });

    describe('createActiveFilterEl', () => {
        it('creates a button with correct data-filter attribute', () => {
            const btn = af.createActiveFilterEl({ paramName: 'size', label: 'Large', option: null });
            expect(btn.getAttribute('data-filter')).toBe('size');
        });

        it('sets data-option when option is provided', () => {
            const btn = af.createActiveFilterEl({ paramName: 'color', label: 'Red', option: 'red' });
            expect(btn.getAttribute('data-option')).toBe('red');
        });

        it('does not set data-option when option is null', () => {
            const btn = af.createActiveFilterEl({ paramName: 'size', label: 'L', option: null });
            expect(btn.getAttribute('data-option')).toBeNull();
        });
    });
});
