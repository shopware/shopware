import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Shopware } from 'shopware';

import 'shopware';
const { default: MultiSelectFilter } = await import('./MultiSelectFilter');

function buildEl({ checked = [] } = {}) {
    const el = document.createElement('div');
    el.innerHTML = `
        <input class="sw-multi-select-filter__search" type="text" placeholder="Search...">
        <ul>
            <li class="sw-multi-select-filter__list-item">
                <input type="checkbox" class="sw-multi-select-filter__list-item-checkbox"
                       name="properties" id="opt-red" value="red"${checked.includes('red') ? ' checked' : ''}>
                <label for="opt-red">Red</label>
            </li>
            <li class="sw-multi-select-filter__list-item">
                <input type="checkbox" class="sw-multi-select-filter__list-item-checkbox"
                       name="properties" id="opt-blue" value="blue"${checked.includes('blue') ? ' checked' : ''}>
                <label for="opt-blue">Blue</label>
            </li>
            <li class="sw-multi-select-filter__list-item">
                <input type="checkbox" class="sw-multi-select-filter__list-item-checkbox"
                       name="properties" id="opt-green" value="green"${checked.includes('green') ? ' checked' : ''}>
                <label for="opt-green">Green</label>
            </li>
        </ul>
    `;
    return el;
}

describe('MultiSelectFilter', () => {
    let el;
    let filter;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl();
        filter = new MultiSelectFilter(el, {});
        filter.init();
    });

    describe('handleSearchInput', () => {
        it('hides options that do not match the search term', () => {
            const searchInput = el.querySelector('.sw-multi-select-filter__search');
            searchInput.value = 'red';
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));

            const items = el.querySelectorAll('.sw-multi-select-filter__list-item');
            expect(items[0].classList.contains('is--hidden')).toBe(false); // Red
            expect(items[1].classList.contains('is--hidden')).toBe(true);  // Blue
            expect(items[2].classList.contains('is--hidden')).toBe(true);  // Green
        });

        it('shows all options when search term is empty', () => {
            const searchInput = el.querySelector('.sw-multi-select-filter__search');
            searchInput.value = 'red';
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));

            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));

            const items = el.querySelectorAll('.sw-multi-select-filter__list-item');
            items.forEach(item => expect(item.classList.contains('is--hidden')).toBe(false));
        });

        it('emits MultiSelectFilter:Search with the search term', () => {
            const searchInput = el.querySelector('.sw-multi-select-filter__search');
            searchInput.value = 'blue';
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('MultiSelectFilter:Search', 'blue');
        });

        it('does not emit MultiSelectFilter:Search when search term is empty', () => {
            const searchInput = el.querySelector('.sw-multi-select-filter__search');
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));

            expect(Shopware.emit).not.toHaveBeenCalledWith('MultiSelectFilter:Search', expect.anything());
        });

        it('uses the intercepted search term', () => {
            vi.mocked(Shopware.emitInterception).mockReturnValueOnce({ searchTerm: 'intercepted' });

            const searchInput = el.querySelector('.sw-multi-select-filter__search');
            searchInput.value = 'original';
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('MultiSelectFilter:Search', 'intercepted');
        });
    });

    describe('handleOptionChange', () => {
        it('adds to activeOptions when checkbox is checked', () => {
            const checkbox = el.querySelector('#opt-red');
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            expect(filter.activeOptions).toContain('red');
        });

        it('removes from activeOptions when checkbox is unchecked', () => {
            const checkbox = el.querySelector('#opt-red');
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            checkbox.checked = false;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            expect(filter.activeOptions).not.toContain('red');
        });

        it('emits Filter:Change', () => {
            const checkbox = el.querySelector('#opt-blue');
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('Filter:Change', expect.objectContaining({
                paramName: 'properties',
                option: 'blue',
            }));
        });

        it('emits MultiSelectFilter:Change', () => {
            const checkbox = el.querySelector('#opt-red');
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            expect(Shopware.emit).toHaveBeenCalledWith('MultiSelectFilter:Change', expect.objectContaining({
                paramName: 'properties',
            }));
        });

        it('accumulates multiple active options', () => {
            el.querySelector('#opt-red').checked = true;
            el.querySelector('#opt-red').dispatchEvent(new Event('change', { bubbles: true }));
            el.querySelector('#opt-blue').checked = true;
            el.querySelector('#opt-blue').dispatchEvent(new Event('change', { bubbles: true }));

            expect(filter.activeOptions).toContain('red');
            expect(filter.activeOptions).toContain('blue');
        });
    });

    describe('handleFilterRemove', () => {
        it('unchecks the option input and removes it from activeOptions', () => {
            // First check red
            const checkbox = el.querySelector('#opt-red');
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            filter.handleFilterRemove({ paramName: 'properties', option: 'red' });

            expect(checkbox.checked).toBe(false);
            expect(filter.activeOptions).not.toContain('red');
        });

        it('ignores removal for a different paramName', () => {
            el.querySelector('#opt-red').checked = true;
            el.querySelector('#opt-red').dispatchEvent(new Event('change', { bubbles: true }));

            filter.handleFilterRemove({ paramName: 'other', option: 'red' });

            expect(filter.activeOptions).toContain('red');
        });
    });

    describe('reset', () => {
        it('unchecks all checkboxes and clears activeOptions', () => {
            el.querySelector('#opt-red').checked = true;
            el.querySelector('#opt-red').dispatchEvent(new Event('change', { bubbles: true }));
            el.querySelector('#opt-blue').checked = true;
            el.querySelector('#opt-blue').dispatchEvent(new Event('change', { bubbles: true }));

            filter.reset();

            const inputs = el.querySelectorAll('.sw-multi-select-filter__list-item-checkbox');
            inputs.forEach(input => expect(input.checked).toBe(false));
            expect(filter.activeOptions).toHaveLength(0);
        });
    });

    describe('getLabelFromInput', () => {
        it('returns the label text for a plain option', () => {
            const input = el.querySelector('#opt-red');
            expect(filter.getLabelFromInput(input)).toBe('Red');
        });

        it('returns paramName when no label element is found', () => {
            const orphanInput = document.createElement('input');
            orphanInput.id = 'no-label';
            orphanInput.setAttribute('name', 'properties');
            el.appendChild(orphanInput);

            expect(filter.getLabelFromInput(orphanInput)).toBe('properties');
        });
    });
});
