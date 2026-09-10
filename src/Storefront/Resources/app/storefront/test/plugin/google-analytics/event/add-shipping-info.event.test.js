import AddShippingInfoEvent from 'src/plugin/google-analytics/events/add-shipping-info.event';

describe('plugin/google-analytics/events/add-shipping-info.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
        window.activeRoute = 'frontend.checkout.confirm.page';
        window.sessionStorage.clear();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('fires only once per checkout when the auto-submit form reloads the page', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-sku="product-123"
                    data-name="Test Product"
                    data-quantity="1"
                    data-price="49.99">
                </span>
            </div>
            <div class="shipping-method-radio">
                <input type="radio" class="shipping-method-input" checked>
                <div class="shipping-method-description">
                    <strong>Express Shipping</strong>
                </div>
            </div>
        `;

        // selecting a shipping method auto-submits and reloads the confirm page,
        // which runs the event again for the same checkout
        new AddShippingInfoEvent().execute();
        new AddShippingInfoEvent().execute();
        new AddShippingInfoEvent().execute();

        expect(window.gtag).toHaveBeenCalledTimes(1);
    });

    test('supports returns true on checkout confirm page', () => {
        expect(new AddShippingInfoEvent().supports('', '', 'frontend.checkout.confirm.page')).toBe(true);
    });

    test('supports returns false on other pages', () => {
        expect(new AddShippingInfoEvent().supports('', '', 'frontend.checkout.cart.page')).toBe(false);
        expect(new AddShippingInfoEvent().supports('', '', 'frontend.detail.page')).toBe(false);
    });

    test('fires add_shipping_info event with shipping tier and line items', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-sku="product-123"
                    data-name="Test Product"
                    data-quantity="2"
                    data-price="99.99"
                    data-brand="Test Brand"
                    data-category-1="Category 1">
                </span>
            </div>
            <div class="shipping-method-radio">
                <input type="radio" class="shipping-method-input" checked>
                <div class="shipping-method-description">
                    <strong>Express Shipping</strong>
                </div>
            </div>
        `;

        new AddShippingInfoEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_shipping_info', {
            'currency': 'EUR',
            'value': 199.98,
            'shipping_tier': 'Express Shipping',
            'items': [
                {
                    item_id: 'product-123',
                    item_name: 'Test Product',
                    quantity: 2,
                    price: 99.99,
                    item_brand: 'Test Brand',
                    item_category: 'Category 1',
                },
            ],
        });
    });

    test('reports the applied promotion codes as coupon', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR">
                <span class="hidden-line-item-coupon" data-code="SAVE20"></span>
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-sku="product-123"
                    data-name="Test Product"
                    data-quantity="1"
                    data-price="99.99">
                </span>
            </div>
            <div class="shipping-method-radio">
                <input type="radio" class="shipping-method-input" checked>
                <div class="shipping-method-description">
                    <strong>Express Shipping</strong>
                </div>
            </div>
        `;

        new AddShippingInfoEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'add_shipping_info', expect.objectContaining({
            'coupon': 'SAVE20',
        }));
    });

    test('does not fire event when no shipping form exists (digital-only order)', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="49.99">
                <span class="hidden-line-item"
                    data-id="digital-product-123"
                    data-name="Digital Product"
                    data-quantity="1"
                    data-price="49.99">
                </span>
            </div>
        `;

        new AddShippingInfoEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('does not fire event when no line items exist', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="0"></div>
            <div class="shipping-method-radio">
                <input type="radio" class="shipping-method-input" checked>
                <div class="shipping-method-description">
                    <strong>Standard</strong>
                </div>
            </div>
        `;

        new AddShippingInfoEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });
});

