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
    el.innerHTML = '<div class="sw-product-listing__grid"><div class="sw-product-card"></div></div>';

    // The `ShopwareComponent` test double does not call `init()` from its constructor.
    const listing = new ProductListing(el);
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
        delete globalThis.event;
        window.location.search = '';
        vi.restoreAllMocks();
        vi.useRealTimers();
    });

    it('declares listingLayout as the query parameter it emits', () => {
        expect(LayoutSwitch.options.paramName).toBe(LISTING_LAYOUT_PARAM);
    });

    it('emits the listingLayout parameter name together with the clicked layout', () => {
        const { component, horizontalButton } = createLayoutSwitch();

        // `changeLayout` reads `event.currentTarget` off the legacy global that a browser populates during
        // event dispatch and happy-dom does not, so this shim supplies what the browser would.
        globalThis.event = { currentTarget: horizontalButton };

        component.onChangeLayout({ currentTarget: horizontalButton });

        expect(Shopware.emit).toHaveBeenCalledWith('LayoutSwitch:Change', LISTING_LAYOUT_PARAM, 'horizontal');
    });

    // `ProductListing` writes the layout key twice from two independent option objects — once under the name
    // `LayoutSwitch` emitted, once under its own `layoutParamName`. Asserting the whole pushed parameter map
    // is what makes a half rename fail: it would write `layout` alongside `listingLayout`, overwriting the
    // seeded legacy value, while a key-presence check on `listingLayout` alone would still pass.
    it('writes only listingLayout and carries a seeded legacy layout parameter through untouched', () => {
        vi.useFakeTimers();
        window.location.search = '?layout=default';
        const pushState = vi.spyOn(window.history, 'pushState');
        const listing = createProductListing();

        listing.handleLayoutChange(LayoutSwitch.options.paramName, 'horizontal');
        // The second write sits behind a 200ms grid-transition timeout.
        vi.advanceTimersByTime(200);

        expect(pushState).toHaveBeenCalledOnce();
        expect(lastPushedParams(pushState)).toEqual({ layout: 'default', listingLayout: 'horizontal' });
    });
});
