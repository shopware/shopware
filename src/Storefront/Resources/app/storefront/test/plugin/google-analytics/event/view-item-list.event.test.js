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
        document.body.innerHTML = `<div class="cms-element-product-listing-wrapper"></div>`;
        expect(new ViewItemListEvent().supports()).toBe(true);
    });

    test('event is not supported when listing wrapper is missing', () => {
        document.body.innerHTML = `<div class="other-content"></div>`;
        expect(new ViewItemListEvent().supports()).toBe(false);
    });

    test('fires view_item_list with currency, value, and categories', () => {
        document.body.innerHTML = `
            <nav aria-label="breadcrumb">
                <span class="breadcrumb-title">Electronics</span>
                <span class="breadcrumb-title">Computers</span>
            </nav>
            <div class="cms-element-product-listing-wrapper">
                <div class="product-box" data-product-information='{ "id": "product-1", "name": "Laptop", "price": 999.99 }'></div>
                <div class="product-box" data-product-information='{ "id": "product-2", "name": "Desktop", "price": 1499.99 }'></div>
            </div>
        `;

        new ViewItemListEvent().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item_list', {
            'currency': 'EUR',
            'value': '2499.98',
            'items': [
                { id: 'product-1', name: 'Laptop', price: 999.99, item_category: 'Electronics', item_category2: 'Computers' },
                { id: 'product-2', name: 'Desktop', price: 1499.99, item_category: 'Electronics', item_category2: 'Computers' },
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
            expect.any(Function)
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
});
