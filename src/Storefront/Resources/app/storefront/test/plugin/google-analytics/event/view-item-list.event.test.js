import ViewItemListEvent from 'src/plugin/google-analytics/events/view-item-list.event';

describe('plugin/google-analytics/events/view-item-list.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
        window.currencyIsoCode = 'EUR';
        window.PluginManager = {
            getPluginInstances: jest.fn(() => []),
            initializePluginsInParentElement: jest.fn(),
        };
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('event is supported when listing wrapper is in the HTML', () => {
        document.body.innerHTML = '<div class="cms-element-product-listing-wrapper"></div>';
        expect(new ViewItemListEvent().supports()).toBe(true);
    });

    test('event is not supported when listing wrapper is missing', () => {
        document.body.innerHTML = '<div class="other-content"></div>';
        expect(new ViewItemListEvent().supports()).toBe(false);
    });

    test('fires view_item_list with currency, value, and categories', () => {
        document.body.innerHTML = `
            <nav aria-label="breadcrumb">
                <span class="breadcrumb-title">Electronics</span>
                <span class="breadcrumb-title">Computers</span>
            </nav>
            <div class="cms-element-product-listing-wrapper">
                <div class="product-box" data-product-information='{ "id": "1", "name": "Laptop", "price": 999.99, "sku": "product-1" }'></div>
                <div class="product-box" data-product-information='{ "id": "2", "name": "Desktop", "price": 1499.99, "sku": "product-2" }'></div>
            </div>
        `;

        new ViewItemListEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item_list', {
            'currency': 'EUR',
            'value': 2499.98,
            'items': [
                { item_id: 'product-1', item_name: 'Laptop', price: 999.99, item_category: 'Electronics', item_category2: 'Computers' },
                { item_id: 'product-2', item_name: 'Desktop', price: 1499.99, item_category: 'Electronics', item_category2: 'Computers' },
            ],
        });
    });

    test('reports the variant options of variant products', () => {
        document.body.innerHTML = `
            <div class="cms-element-product-listing-wrapper">
                <div class="product-box" data-product-information='{ "id": "1", "name": "Shirt", "price": 20, "sku": "SW10000.1", "variant": "Red, L" }'></div>
                <div class="product-box" data-product-information='{ "id": "2", "name": "Mug", "price": 5, "sku": "SW10001", "variant": "" }'></div>
            </div>
        `;

        new ViewItemListEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item_list', {
            'currency': 'EUR',
            'value': 25,
            'items': [
                { item_id: 'SW10000.1', item_name: 'Shirt', price: 20, item_variant: 'Red, L' },
                { item_id: 'SW10001', item_name: 'Mug', price: 5 },
            ],
        });
    });

    test('does not fire event when no product items are found', () => {
        document.body.innerHTML = `
            <div class="cms-element-product-listing-wrapper">
                <!-- No product items -->
            </div>
        `;

        new ViewItemListEvent().execute();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('subscribes to Listing plugin for AJAX updates and fires event on listing change', () => {
        document.body.innerHTML = `
            <div class="cms-element-product-listing-wrapper">
                <div class="product-box" data-product-information='{ "id": "product-1", "name": "Laptop", "price": 999.99 }'></div>
            </div>
        `;

        const mockEmitter = {
            subscribe: jest.fn(),
        };

        const mockPluginInstance = {
            $emitter: mockEmitter,
        };

        window.PluginManager.getPluginInstances.mockReturnValue([mockPluginInstance]);

        const event = new ViewItemListEvent();
        event.execute();

        // Verify it fired on initial page load
        expect(window.gtag).toHaveBeenCalledTimes(1);

        // Verify it subscribed to the Listing plugin's afterRenderResponse event
        expect(mockEmitter.subscribe).toHaveBeenCalledWith(
            'Listing/afterRenderResponse',
            expect.any(Function),
        );

        // Simulate a listing change (pagination/filter)
        const subscribeCallback = mockEmitter.subscribe.mock.calls[0][1];
        subscribeCallback();

        // Verify it fired again after listing change
        expect(window.gtag).toHaveBeenCalledTimes(2);
    });

    test('returns correct plugin name and events for EventAwareAnalyticsEvent', () => {
        const event = new ViewItemListEvent();

        expect(event.getPluginName()).toBe('Listing');
        expect(event.getEvents()).toHaveProperty('Listing/afterRenderResponse');
    });

    test('reports only documented item properties, whatever else the product box carries', () => {
        const information = {
            id: 'product-123',
            sku: 'SW10000',
            name: 'Test Product',
            brand: 'Test Brand',
            variant: 'Red, L',
            price: '19.99',
            // a theme or a later feature can add keys the GA4 item schema does not define
            internalNote: 'not an item property',
        };

        document.body.innerHTML = `
            <div class="cms-element-product-listing-wrapper">
                <div class="product-box" data-product-information='${JSON.stringify(information)}'></div>
            </div>
        `;

        new ViewItemListEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item_list', expect.objectContaining({
            'items': [{
                'item_id': 'SW10000',
                'item_name': 'Test Product',
                'item_brand': 'Test Brand',
                'item_variant': 'Red, L',
                'price': 19.99,
            }],
        }));
    });

    test('skips a product box with an unreadable product information attribute', () => {
        document.body.innerHTML = `
            <div class="cms-element-product-listing-wrapper">
                <div class="product-box" data-product-information='{"broken'></div>
                <div class="product-box" data-product-information='{"sku":"SW10000","name":"Test Product","price":"19.99"}'></div>
            </div>
        `;

        new ViewItemListEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item_list', expect.objectContaining({
            'items': [{
                'item_id': 'SW10000',
                'item_name': 'Test Product',
                'price': 19.99,
            }],
        }));
    });
});
