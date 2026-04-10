import AddToCartByNumberEvent from 'src/plugin/google-analytics/events/add-to-cart-by-number.event';

describe('plugin/google-analytics/events/add-to-cart-by-number.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true on cart page', () => {
        expect(new AddToCartByNumberEvent().supports('', '', 'frontend.checkout.cart.page')).toBe(true);
    });

    test('supports returns false on other pages', () => {
        expect(new AddToCartByNumberEvent().supports('', '', 'frontend.detail.page')).toBe(false);
        expect(new AddToCartByNumberEvent().supports('', '', 'frontend.home.page')).toBe(false);
    });

    test('fires add_to_cart event with currency when form is submitted', () => {
        document.body.innerHTML = `
            <form class="cart-add-product">
                <input class="form-control" value="SW10001">
                <button type="submit">Add</button>
            </form>
            <div class="hidden-line-items-information" data-currency="EUR"></div>
        `;

        const event = new AddToCartByNumberEvent();
        event.execute();

        const form = document.querySelector('.cart-add-product');
        form.dispatchEvent(new Event('submit'));

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_cart', {
            'currency': 'EUR',
            'items': [
                {
                    'id': 'SW10001',
                    'quantity': 1,
                },
            ],
        });
    });

    test('does not register listener when form is missing', () => {
        document.body.innerHTML = `<div>No form here</div>`;

        const event = new AddToCartByNumberEvent();
        event.execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });
});

