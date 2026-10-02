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
            'value': 199.98,
            'items': [{
                'item_id': 'product-123',
                'item_name': 'Test Product',
                'quantity': 2,
                'price': 99.99,
                'item_brand': 'Test Brand',
                'item_category': 'Category 1',
            }],
        });
    });

    test('matches a line item whose id differs from its product id', () => {
        // a Store API client or an extension can add a product under its own line item id, which the
        // remove button carries while the hidden line item reports the product
        document.body.innerHTML = `
            <button class="line-item-remove-button" data-product-id="custom-line-item"></button>
            <div class="hidden-line-items-information" data-currency="EUR">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-line-item-id="custom-line-item"
                    data-sku="SW10000"
                    data-name="Test Product"
                    data-quantity="1"
                    data-price="10">
                </span>
            </div>
        `;

        new RemoveFromCart().execute();
        document.querySelector('.line-item-remove-button').click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_cart', expect.objectContaining({
            'items': [expect.objectContaining({ 'item_id': 'SW10000', 'item_name': 'Test Product' })],
        }));
    });

    test('matches a line item id with characters a selector would have to escape', () => {
        document.body.innerHTML = `
            <button class="line-item-remove-button" data-product-id='custom"item'></button>
            <div class="hidden-line-items-information" data-currency="EUR">
                <span class="hidden-line-item" data-id="product-123" data-line-item-id='custom"item' data-sku="SW10000" data-quantity="1" data-price="10"></span>
            </div>
        `;

        new RemoveFromCart().execute();
        document.querySelector('.line-item-remove-button').click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'remove_from_cart', expect.objectContaining({
            'items': [expect.objectContaining({ 'item_id': 'SW10000' })],
        }));
    });

    test('does not fire an event for a line item that is not a product', () => {
        // only product line items are rendered into the hidden container, so a discount that is
        // removed has no match there and is not an item GA4 reports
        document.body.innerHTML = `
            <button class="line-item-remove-button" data-product-id="promotion-123"></button>
            <div class="hidden-line-items-information" data-currency="EUR"></div>
        `;

        const event = new RemoveFromCart();
        event.execute();

        const button = document.querySelector('.line-item-remove-button');
        button.click();

        expect(window.gtag).not.toHaveBeenCalled();
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

