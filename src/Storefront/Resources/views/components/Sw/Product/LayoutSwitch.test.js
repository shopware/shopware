import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
// Import order is load-bearing: this module's side effect installs the `globalThis.ShopwareComponent`
// that both components below extend as a bare global while their own module is evaluated.
import { Shopware } from 'shopware';
import LayoutSwitch from './LayoutSwitch';
import ProductListing from './Listing';

const LISTING_LAYOUT_PARAM = 'listingLayout';

function createLayoutSwitch() {
    const el = document.createElement('div');
    el.innerHTML = '<button data-layout="horizontal"></button><button data-layout="default"></button>';

    // The `ShopwareComponent` test double does not call `init()` from its constructor.
    const component = new LayoutSwitch(el);
    component.init();

    return { component, horizontalButton: el.querySelector('[data-layout="horizontal"]') };
}

function createProductListing() {
    const el = document.createElement('div');
    el.dataset.elementId = 'listing';
    el.innerHTML = `
        <div class="sw-product-layout-switch">
            <button data-layout="horizontal"></button>
            <button data-layout="default"></button>
        </div>
        <div class="sw-product-listing__grid">
            <div class="sw-grid-container__inner">
                <div class="sw-product-card"></div>
            </div>
        </div>
    `;
    document.body.append(el);

    // The `ShopwareComponent` test double does not call `init()` from its constructor.
    const listing = new ProductListing(el, { contentUrl: '/content/category/test?elementId=listing' });
    listing.init();

    return listing;
}

function lastPushedParams(pushState) {
    const calls = pushState.mock.calls;
    const url = new URL(calls[calls.length - 1][2], window.location.origin);

    return Object.fromEntries(url.searchParams);
}

describe('Sw:Product:LayoutSwitch', () => {
    beforeEach(() => {
        window.location.search = '';
        Shopware.emit.mockClear();
    });

    afterEach(() => {
        document.body.replaceChildren();
        window.location.search = '';
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('declares listingLayout as the query parameter it emits', () => {
        expect(LayoutSwitch.options.paramName).toBe(LISTING_LAYOUT_PARAM);
    });

    it('emits the listingLayout parameter name together with the clicked layout', () => {
        const { component, horizontalButton } = createLayoutSwitch();

        horizontalButton.click();

        expect(Shopware.emit).toHaveBeenCalledWith('LayoutSwitch:Change', LISTING_LAYOUT_PARAM, 'horizontal');
        expect(horizontalButton.getAttribute('aria-pressed')).toBe('true');
        expect(component.el.querySelector('[data-layout="default"]').getAttribute('aria-pressed')).toBe('false');
    });

    it.each([
        ['horizontal', 'minmax(0, 1fr)'],
        ['default', 'repeat(4, minmax(0, 1fr))'],
    ])('replaces the grid when switching to the %s presentation', async (layout, tracks) => {
        window.location.search = '?layout=legacy&order=name-asc&p=2';
        const pushState = vi.spyOn(window.history, 'pushState');
        const fetchListing = vi.fn().mockResolvedValue({
            text: async () => `
                <div data-element-id="listing">
                    <div class="sw-product-layout-switch">
                        <button class="is--active" data-layout="${layout}"></button>
                    </div>
                    <div class="sw-grid-container__inner" style="--sw-grid-columns-lg: ${tracks}">
                        <div class="sw-product-card is--layout-${layout}"></div>
                    </div>
                </div>
            `,
        });
        vi.stubGlobal('fetch', fetchListing);
        const listing = createProductListing();
        listing.el.querySelector(`[data-layout="${layout}"]`).focus();

        listing.handleLayoutChange(LayoutSwitch.options.paramName, layout);

        const expectedParams = { layout: 'legacy', order: 'name-asc', p: '2', listingLayout: layout };
        expect(pushState).toHaveBeenCalledOnce();
        expect(lastPushedParams(pushState)).toEqual(expectedParams);
        const [url] = fetchListing.mock.calls[0];
        expect(Object.fromEntries(url.searchParams)).toEqual({ elementId: 'listing', ...expectedParams });

        await vi.waitFor(() => {
            const fresh = document.querySelector('[data-element-id="listing"]');
            expect(fresh).not.toBe(listing.el);
            expect(fresh.querySelector('.sw-grid-container__inner').style.getPropertyValue('--sw-grid-columns-lg')).toBe(tracks);
            expect(fresh.querySelector(`.is--layout-${layout}`)).not.toBeNull();
            expect(document.activeElement).toBe(fresh.querySelector('[data-layout].is--active'));
        });
    });
});
