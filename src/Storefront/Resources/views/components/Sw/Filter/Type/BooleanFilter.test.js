import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Shopware } from 'shopware';

import 'shopware';
const { default: BooleanFilter } = await import('./BooleanFilter');

function buildEl({ checked = false } = {}) {
    const el = document.createElement('div');
    el.innerHTML = `
        <input type="checkbox" class="sw-boolean-filter__input" name="active" id="active-filter"${checked ? ' checked' : ''}>
        <label for="active-filter">Active</label>
    `;
    return el;
}

describe('BooleanFilter', () => {
    let el;
    let filter;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl();
        filter = new BooleanFilter(el, {});
        filter.init();
    });

    describe('handleChange', () => {
        it('emits Filter:Change with the checked value', () => {
            const checkbox = el.querySelector('.sw-boolean-filter__input');
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Change', expect.objectContaining({
                paramName: 'active',
                value: true,
            }));
        });

        it('emits BooleanFilter:Change', () => {
            const checkbox = el.querySelector('.sw-boolean-filter__input');
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('BooleanFilter:Change', expect.objectContaining({
                paramName: 'active',
                value: true,
            }));
        });

        it('emits Filter:Change with false when unchecked', () => {
            const checkbox = el.querySelector('.sw-boolean-filter__input');
            checkbox.checked = false;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Change', expect.objectContaining({
                value: false,
            }));
        });

        it('passes the label from the associated <label> element', () => {
            const checkbox = el.querySelector('.sw-boolean-filter__input');
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Change', expect.objectContaining({
                label: 'Active',
            }));
        });

        it('dispatches a BooleanFilter:Update custom event on the element', () => {
            const handler = vi.fn();
            el.addEventListener('BooleanFilter:Update', handler);

            const checkbox = el.querySelector('.sw-boolean-filter__input');
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            expect(handler).toHaveBeenCalled();
        });

        it('uses the intercepted values when emitting', () => {
            vi.mocked(Shopware.emitInterception).mockReturnValueOnce({
                paramName: 'active',
                value: false,
                label: 'Intercepted',
            });

            const checkbox = el.querySelector('.sw-boolean-filter__input');
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Change', expect.objectContaining({
                label: 'Intercepted',
            }));
        });
    });

    describe('handleFilterRemove', () => {
        it('unchecks the checkbox when the matching paramName is removed', () => {
            const checkbox = el.querySelector('.sw-boolean-filter__input');
            checkbox.checked = true;

            filter.handleFilterRemove({ paramName: 'active' });

            expect(checkbox.checked).toBe(false);
        });

        it('does not uncheck when a different paramName is removed', () => {
            const checkbox = el.querySelector('.sw-boolean-filter__input');
            checkbox.checked = true;

            filter.handleFilterRemove({ paramName: 'other' });

            expect(checkbox.checked).toBe(true);
        });
    });

    describe('getLabel', () => {
        it('returns the text of the associated label element', () => {
            expect(filter.getLabel()).toBe('Active');
        });

        it('falls back to paramName when no label element exists', () => {
            const el2 = document.createElement('div');
            el2.innerHTML = '<input type="checkbox" class="sw-boolean-filter__input" name="featured" id="no-label">';
            const f2 = new BooleanFilter(el2, {});
            f2.init();

            expect(f2.getLabel()).toBe('featured');
        });
    });
});
