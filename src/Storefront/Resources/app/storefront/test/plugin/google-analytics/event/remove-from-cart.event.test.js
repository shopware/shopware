import RemoveFromCart from 'src/plugin/google-analytics/events/remove-from-cart.event';

describe('plugin/google-analytics/events/remove-from-cart.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true on any page', () => {
        expect(new RemoveFromCart().supports('', '', 'frontend.checkout.cart.page')).toBe(true);
        expect(new RemoveFromCart().supports('', '', 'frontend.detail.page')).toBe(true);
    });

    test('fires remove_from_cart event with currency and value when remove button is clicked', () => {
        document.body.innerHTML = `
            <button class="line-item-remove-button" data-product-id="product-123"></button>
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-name="Test Product"
                    data-quantity="2"
                    data-price="99.99"
                    data-brand="Test Brand"
                    data-category-1="Category 1">
                </span>
            </div>
        `;

        const event = new RemoveFromCart();
        event.execute();

        const button = document.querySelector('.line-item-remove-button');
        button.click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_cart', {
            'currency': 'EUR',
            'value': '199.98',
            'items': [{
                'id': 'product-123',
                'name': 'Test Product',
                'quantity': '2',
                'price': '99.99',
                'brand': 'Test Brand',
                'item_category': 'Category 1',
            }],
        });
    });

    test('fires event with currency and just product ID when hidden line item is not found', () => {
        document.body.innerHTML = `
            <button class="line-item-remove-button" data-product-id="product-123"></button>
            <div class="hidden-line-items-information" data-currency="EUR"></div>
        `;

        const event = new RemoveFromCart();
        event.execute();

        const button = document.querySelector('.line-item-remove-button');
        button.click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_cart', {
            'currency': 'EUR',
            'items': [{ 'id': 'product-123' }],
        });
    });

    test('does not fire event when clicking non-remove button', () => {
        document.body.innerHTML = `
            <button class="other-button" data-product-id="product-123"></button>
        `;

        const event = new RemoveFromCart();
        event.execute();

        const button = document.querySelector('.other-button');
        button.click();

        expect(window.gtag).not.toHaveBeenCalled();
    });
});

