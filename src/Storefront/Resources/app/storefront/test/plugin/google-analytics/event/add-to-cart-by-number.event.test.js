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
            <div class="cart-add-product-container">
                <form action="/checkout/product/add-by-number" method="post">
                    <input type="hidden" name="redirectTo" value="frontend.checkout.cart.page">
                    <input type="text" name="number" class="form-control" value="SW10001">
                    <button type="submit">Add</button>
                </form>
            </div>
            <div class="hidden-line-items-information" data-currency="EUR"></div>
        `;

        const event = new AddToCartByNumberEvent();
        event.execute();

        const form = document.querySelector('.cart-add-product-container form');
        form.dispatchEvent(new Event('submit'));

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_to_cart', {
            'currency': 'EUR',
            'items': [
                {
                    'item_id': 'SW10001',
                    'quantity': 1,
                },
            ],
        });
    });

    test.each([
        ['the number field is empty', '<input type="text" name="number" class="form-control" value="">'],
        ['a theme removed the number field', '<input type="text" name="productNumber" class="form-control" value="SW10001">'],
    ])('does not fire add_to_cart when %s', (_, field) => {
        document.body.innerHTML = `
            <div class="cart-add-product-container">
                <form action="/checkout/product/add-by-number" method="post">
                    ${field}
                    <button type="submit">Add</button>
                </form>
            </div>
            <div class="hidden-line-items-information" data-currency="EUR"></div>
        `;

        new AddToCartByNumberEvent().execute();
        document.querySelector('.cart-add-product-container form').dispatchEvent(new Event('submit'));

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not register listener when form is missing', () => {
        document.body.innerHTML = '<div>No form here</div>';

        const event = new AddToCartByNumberEvent();
        event.execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });
});

