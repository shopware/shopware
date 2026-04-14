import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

import 'shopware';

// window.router must be set before CartWidget is imported because the static
// options object reads from it at class-definition time.
globalThis.router = { 'frontend.checkout.info': '/checkout/info' };

const { default: CartWidget } = await import('./CartWidget');

function buildEl() {
    const el = document.createElement('div');
    el.innerHTML = '<span class="sw-header-widget__label"></span>';
    return el;
}

function mockFetch(status, html = '') {
    return vi.spyOn(globalThis, 'fetch').mockResolvedValue({
        status,
        text: () => Promise.resolve(html),
    });
}

describe('CartWidget', () => {
    let el;
    let widget;
    let fetchSpy;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl();
    });

    afterEach(() => {
        fetchSpy?.mockRestore();
    });

    describe('fetchCartInfo', () => {
        it('updates the label with the cart total from the HTML response', async () => {
            fetchSpy = mockFetch(200, '<span class="header-cart-total">3 Items</span>');
            widget = new CartWidget(el, {});
            widget.init();

            // Wait for the fetch promise chain to resolve.
            await vi.waitFor(() => {
                expect(el.querySelector('.sw-header-widget__label').innerHTML).toBe('3 Items');
            });
        });

        it('renders the empty cart when the response is 204', async () => {
            fetchSpy = mockFetch(204);
            widget = new CartWidget(el, {});
            widget.init();

            await vi.waitFor(() => {
                expect(el.querySelector('.sw-header-widget__label').innerHTML).toBe('0');
            });
        });

        it('renders the empty cart when the response is a 5xx error', async () => {
            fetchSpy = mockFetch(500);
            widget = new CartWidget(el, {});
            widget.init();

            await vi.waitFor(() => {
                expect(el.querySelector('.sw-header-widget__label').innerHTML).toBe('0');
            });
        });

        it('renders the empty cart when the response contains no .header-cart-total element', async () => {
            fetchSpy = mockFetch(200, '<div>No cart total here</div>');
            widget = new CartWidget(el, {});
            widget.init();

            await vi.waitFor(() => {
                expect(el.querySelector('.sw-header-widget__label').innerHTML).toBe('0');
            });
        });

        it('fetches the cart info route', async () => {
            fetchSpy = mockFetch(200, '<span class="header-cart-total">1</span>');
            widget = new CartWidget(el, {});
            widget.init();

            await vi.waitFor(() => expect(fetchSpy).toHaveBeenCalledWith(
                '/checkout/info',
                expect.objectContaining({ headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
            ));
        });
    });

    describe('renderEmptyCart', () => {
        it('sets the label to the emptyValue option', () => {
            fetchSpy = mockFetch(204);
            widget = new CartWidget(el, {});
            widget.init();
            widget.renderEmptyCart();

            expect(el.querySelector('.sw-header-widget__label').innerHTML).toBe('0');
        });

        it('uses a custom emptyValue option', () => {
            fetchSpy = mockFetch(204);
            widget = new CartWidget(el, { emptyValue: '–' });
            widget.init();
            widget.renderEmptyCart();

            expect(el.querySelector('.sw-header-widget__label').innerHTML).toBe('–');
        });
    });
});
