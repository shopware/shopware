import ViewCartEvent from 'src/plugin/google-analytics/events/view-cart.event';

describe('plugin/google-analytics/events/view-cart.event', () => {
    beforeEach(() => {
        jest.useFakeTimers();
        window.gtag = jest.fn();
        window.activeRoute = 'frontend.checkout.cart.page';
        window.PluginManager = {
            getPlugin: jest.fn(),
            initializePluginsInParentElement: jest.fn(),
        };
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
        jest.useRealTimers();
        delete window.trackOffcanvasCart;
        delete window.activeRoute;
    });

    test('supports returns true on cart page', () => {
        expect(new ViewCartEvent().supports('', '', 'frontend.checkout.cart.page')).toBe(true);
    });

    test('supports returns false on other pages when offcanvas tracking disabled', () => {
        window.trackOffcanvasCart = '0';
        expect(new ViewCartEvent().supports('', '', 'frontend.detail.page')).toBe(false);
    });

    test('supports returns true on other pages when offcanvas tracking enabled', () => {
        window.trackOffcanvasCart = '1';
        expect(new ViewCartEvent().supports('', '', 'frontend.detail.page')).toBe(true);
    });

    test('fires view_cart event with line items on cart page', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                <span class="hidden-line-item"
                    data-id="product-123"
                    data-name="Test Product"
                    data-quantity="2"
                    data-price="99.99">
                </span>
            </div>
        `;

        new ViewCartEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_cart', {
            'currency': 'EUR',
            'value': '199.98',
            'items': [
                {
                    id: 'product-123',
                    name: 'Test Product',
                    quantity: '2',
                    price: '99.99',
                    brand: null,
                },
            ],
        });
    });

    test('does not fire event when no line items exist', () => {
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="0"></div>
        `;

        new ViewCartEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('fires view_cart event when offcanvas opens and tracking enabled', () => {
        window.trackOffcanvasCart = '1';
        window.activeRoute = 'frontend.detail.page';
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="99.99">
                <span class="hidden-line-item"
                    data-id="product-456"
                    data-name="Another Product"
                    data-quantity="1"
                    data-price="99.99">
                </span>
            </div>
        `;

        const event = new ViewCartEvent();
        event.execute();

        // Simulate offcanvas cart change (happens on open, quantity change, etc.)
        event._onOffCanvasCartChange();
        jest.advanceTimersByTime(50);

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_cart', {
            'currency': 'EUR',
            'value': '99.99',
            'items': [
                {
                    id: 'product-456',
                    name: 'Another Product',
                    quantity: '1',
                    price: '99.99',
                    brand: null,
                },
            ],
        });
    });

    test('subscribes to both offCanvasOpened and registerEvents for cart content updates', () => {
        window.trackOffcanvasCart = '1';
        window.activeRoute = 'frontend.detail.page';
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="99.99">
                <span class="hidden-line-item"
                    data-id="product-456"
                    data-name="Another Product"
                    data-quantity="1"
                    data-price="99.99">
                </span>
            </div>
        `;

        const mockEmitter = {
            subscribe: jest.fn(),
        };

        const mockPluginInstance = {
            $emitter: mockEmitter,
        };

        window.PluginManager.getPlugin.mockReturnValue({
            get: jest.fn().mockReturnValue([mockPluginInstance]),
        });

        const event = new ViewCartEvent();
        event.execute();

        // Verify subscriptions to both events
        expect(mockEmitter.subscribe).toHaveBeenCalledWith(
            'offCanvasOpened',
            expect.any(Function)
        );
        expect(mockEmitter.subscribe).toHaveBeenCalledWith(
            'registerEvents',
            expect.any(Function)
        );
    });

    test('debounces multiple rapid cart change events to avoid duplicates', () => {
        window.trackOffcanvasCart = '1';
        window.activeRoute = 'frontend.detail.page';
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="99.99">
                <span class="hidden-line-item"
                    data-id="product-456"
                    data-name="Another Product"
                    data-quantity="1"
                    data-price="99.99">
                </span>
            </div>
        `;

        const event = new ViewCartEvent();
        event.execute();

        // Simulate multiple rapid events (as would happen when offCanvasOpened and registerEvents both fire)
        event._onOffCanvasCartChange();
        event._onOffCanvasCartChange();
        event._onOffCanvasCartChange();

        // Before debounce timeout, no event should fire
        expect(window.gtag).not.toHaveBeenCalled();

        // After debounce timeout, only one event should fire
        jest.advanceTimersByTime(50);

        expect(window.gtag).toHaveBeenCalledTimes(1);
    });

    test('fires view_cart again when cart content is updated after quantity change', () => {
        window.trackOffcanvasCart = '1';
        window.activeRoute = 'frontend.detail.page';
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="99.99">
                <span class="hidden-line-item"
                    data-id="product-456"
                    data-name="Another Product"
                    data-quantity="1"
                    data-price="99.99">
                </span>
            </div>
        `;

        const event = new ViewCartEvent();
        event.execute();

        // Simulate initial offcanvas open
        event._onOffCanvasCartChange();
        jest.advanceTimersByTime(50);
        expect(window.gtag).toHaveBeenCalledTimes(1);

        // Update cart content (simulate quantity change)
        document.body.innerHTML = `
            <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                <span class="hidden-line-item"
                    data-id="product-456"
                    data-name="Another Product"
                    data-quantity="2"
                    data-price="99.99">
                </span>
            </div>
        `;

        // Simulate cart update event
        event._onOffCanvasCartChange();
        jest.advanceTimersByTime(50);

        expect(window.gtag).toHaveBeenCalledTimes(2);
        expect(window.gtag).toHaveBeenLastCalledWith('event', 'view_cart', {
            'currency': 'EUR',
            'value': '199.98',
            'items': [
                {
                    id: 'product-456',
                    name: 'Another Product',
                    quantity: '2',
                    price: '99.99',
                    brand: null,
                },
            ],
        });
    });
});

