import { describe, it, expect, vi, beforeEach } from 'vitest';
// Importing 'shopware' triggers the mock's globalThis side-effect so that
// window.ShopwareComponent and window.Shopware are set before Panel.js loads.
// 'shopware' is aliased to __mocks__/shopware.ts in vitest.config.ts.
import 'shopware';

// Import after the window globals are populated.
const { default: FilterPanel } = await import('./Panel');

function buildEl(filterCount = 5) {
    const el = document.createElement('div');

    const button = document.createElement('button');
    button.className = 'sw-filter-panel__expand';
    button.innerHTML = `
        <span class="sw-filter-panel__expand-text">Show more</span>
        <span class="sw-filter-panel__collapse-text">Show less</span>
    `;
    el.appendChild(button);

    for (let i = 0; i < filterCount; i++) {
        const item = document.createElement('div');
        item.className = 'sw-filter-item';
        el.appendChild(item);
    }

    return el;
}

describe('FilterPanel', () => {
    let el;
    let panel;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl(5);
        panel = new FilterPanel(el, {});
        panel.init();
    });

    it('hides filters beyond visibleFilterCount on init', () => {
        const items = el.querySelectorAll('.sw-filter-item');
        // Default visibleFilterCount is 3; items at index 3 and 4 must be hidden.
        expect(items[0].classList.contains('is--hidden')).toBe(false);
        expect(items[2].classList.contains('is--hidden')).toBe(false);
        expect(items[3].classList.contains('is--hidden')).toBe(true);
        expect(items[4].classList.contains('is--hidden')).toBe(true);
    });

    it('hides the collapse text and shows the expand text on init', () => {
        const expandText = el.querySelector('.sw-filter-panel__expand-text');
        const collapseText = el.querySelector('.sw-filter-panel__collapse-text');

        expect(expandText.style.display).toBe('inline-block');
        expect(collapseText.style.display).toBe('none');
    });

    it('reveals all filters when the expand button is clicked', () => {
        const button = el.querySelector('.sw-filter-panel__expand');
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        const items = el.querySelectorAll('.sw-filter-item');
        items.forEach(item => {
            expect(item.classList.contains('is--hidden')).toBe(false);
        });
    });

    it('shows the collapse text and hides the expand text after expanding', () => {
        el.querySelector('.sw-filter-panel__expand').dispatchEvent(new MouseEvent('click', { bubbles: true }));

        const expandText = el.querySelector('.sw-filter-panel__expand-text');
        const collapseText = el.querySelector('.sw-filter-panel__collapse-text');

        expect(expandText.style.display).toBe('none');
        expect(collapseText.style.display).toBe('inline-block');
    });

    it('collapses back to visibleFilterCount when the button is clicked again', () => {
        const button = el.querySelector('.sw-filter-panel__expand');
        // Expand first.
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        // Then collapse.
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        const items = el.querySelectorAll('.sw-filter-item');
        expect(items[3].classList.contains('is--hidden')).toBe(true);
        expect(items[4].classList.contains('is--hidden')).toBe(true);
    });

    it('respects a custom visibleFilterCount option', () => {
        const el2 = buildEl(6);
        const p2 = new FilterPanel(el2, { visibleFilterCount: 1 });
        p2.init();

        const items = el2.querySelectorAll('.sw-filter-item');
        expect(items[0].classList.contains('is--hidden')).toBe(false);
        expect(items[1].classList.contains('is--hidden')).toBe(true);
    });

    it('does not hide any filters when all are within visibleFilterCount', () => {
        const el2 = buildEl(2);
        const p2 = new FilterPanel(el2, {});
        p2.init();

        const items = el2.querySelectorAll('.sw-filter-item');
        items.forEach(item => {
            expect(item.classList.contains('is--hidden')).toBe(false);
        });
    });
});
