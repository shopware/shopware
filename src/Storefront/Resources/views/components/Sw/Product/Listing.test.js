import { describe, it, expect, vi, beforeEach } from 'vitest';

import 'shopware';

globalThis.activeNavigationId = 'cat-123';

const { default: ProductListing } = await import('./Listing');

function buildEl() {
    const el = document.createElement('div');
    el.setAttribute('data-element-id', 'listing-el');
    el.innerHTML = `
        <div class="sw-product-listing__grid"></div>
        <div class="sw-product-listing__pagination"></div>
    `;
    return el;
}

describe('ProductListing', () => {
    let el;
    let listing;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl();
        listing = new ProductListing(el, {});
        listing.init();
        // Prevent actual fetch calls in tests by replacing loadListing.
        listing.loadListing = vi.fn();
    });

    describe('handleFilterChange', () => {
        it('sets a simple filter param', () => {
            listing.handleFilterChange({ paramName: 'color', value: 'red' });

            expect(listing.activeParams.color).toBe('red');
        });

        it('resets the page to 1 when a filter changes', () => {
            listing.handleFilterChange({ paramName: 'color', value: 'red' });

            expect(listing.activeParams[listing.options.pageParamName]).toBe(1);
        });

        it('removes a param when value is empty string', () => {
            listing.handleFilterChange({ paramName: 'color', value: 'red' });
            listing.handleFilterChange({ paramName: 'color', value: '' });

            expect(listing.activeParams.color).toBeUndefined();
        });

        it('removes a param when value is null', () => {
            listing.handleFilterChange({ paramName: 'color', value: 'red' });
            listing.handleFilterChange({ paramName: 'color', value: null });

            expect(listing.activeParams.color).toBeUndefined();
        });

        it('accumulates activeOptions into a pipe-separated string', () => {
            listing.handleFilterChange({
                paramName: 'properties',
                activeOptions: ['red', 'blue'],
                removedOptions: [],
            });

            expect(listing.activeParams.properties).toBe('red|blue');
        });

        it('removes an option from an existing pipe-separated value', () => {
            listing.handleFilterChange({ paramName: 'props', activeOptions: ['red', 'blue'], removedOptions: [] });
            listing.handleFilterChange({ paramName: 'props', activeOptions: [], removedOptions: ['red'] });

            expect(listing.activeParams.props).toBe('blue');
        });

        it('calls loadListing', () => {
            listing.handleFilterChange({ paramName: 'color', value: 'red' });

            expect(listing.loadListing).toHaveBeenCalled();
        });
    });

    describe('handleFilterRemove', () => {
        it('removes the entire param when no option is specified', () => {
            listing.activeParams.color = 'red';
            listing.handleFilterRemove({ paramName: 'color' });

            expect(listing.activeParams.color).toBeUndefined();
        });

        it('removes a specific option from a pipe-separated param', () => {
            listing.activeParams.props = 'red|blue|green';
            listing.handleFilterRemove({ paramName: 'props', option: 'blue' });

            expect(listing.activeParams.props).toBe('red|green');
        });

        it('removes the param entirely when the last option is removed', () => {
            listing.activeParams.props = 'red';
            listing.handleFilterRemove({ paramName: 'props', option: 'red' });

            expect(listing.activeParams.props).toBeUndefined();
        });

        it('does nothing when the param does not exist', () => {
            listing.handleFilterRemove({ paramName: 'nonexistent' });

            expect(listing.activeParams.nonexistent).toBeUndefined();
        });

        it('calls loadListing', () => {
            listing.activeParams.color = 'red';
            listing.handleFilterRemove({ paramName: 'color' });

            expect(listing.loadListing).toHaveBeenCalled();
        });
    });

    describe('handlePageChange', () => {
        it('sets the page param', () => {
            listing.handlePageChange(3);

            expect(listing.activeParams[listing.options.pageParamName]).toBe(3);
        });

        it('calls loadListing', () => {
            listing.handlePageChange(2);

            expect(listing.loadListing).toHaveBeenCalled();
        });
    });

    describe('handleSortingChange', () => {
        it('sets the sorting param', () => {
            listing.handleSortingChange('price-asc');

            expect(listing.activeParams[listing.options.sortingParamName]).toBe('price-asc');
        });

        it('calls loadListing', () => {
            listing.handleSortingChange('name-desc');

            expect(listing.loadListing).toHaveBeenCalled();
        });
    });

    describe('handleLayoutChange', () => {
        it('sets the layout param', () => {
            listing.handleLayoutChange('layout', 'horizontal');

            expect(listing.activeParams['layout']).toBe('horizontal');
        });
    });

    describe('getStateFromUrl', () => {
        it('parses existing URL search parameters into activeParams', () => {
            // happy-dom allows setting location.search
            Object.defineProperty(window, 'location', {
                value: { ...window.location, search: '?p=2&order=price-asc' },
                configurable: true,
            });

            listing.getStateFromUrl();

            expect(listing.activeParams.p).toBe('2');
            expect(listing.activeParams.order).toBe('price-asc');

            // Restore default
            Object.defineProperty(window, 'location', {
                value: { ...window.location, search: '' },
                configurable: true,
            });
        });
    });

    describe('changeLayout', () => {
        it('adds the layout class to the grid and product cards', () => {
            vi.useFakeTimers();
            // updateHistory calls new URL(window.location) which fails in happy-dom; stub it.
            listing.updateHistory = vi.fn();

            const grid = el.querySelector('.sw-product-listing__grid');
            grid.innerHTML = `
                <div class="sw-product-card is--layout-default"></div>
                <div class="sw-product-card is--layout-default"></div>
            `;

            listing.changeLayout('horizontal');
            vi.runAllTimers();

            const cards = grid.querySelectorAll('.sw-product-card');
            cards.forEach(card => {
                expect(card.classList.contains('is--layout-horizontal')).toBe(true);
                expect(card.classList.contains('is--layout-default')).toBe(false);
            });

            vi.useRealTimers();
        });
    });
});
