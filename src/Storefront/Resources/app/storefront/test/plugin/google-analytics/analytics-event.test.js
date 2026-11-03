import AnalyticsEvent from 'src/plugin/google-analytics/analytics-event';

describe('plugin/google-analytics/analytics-event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
        window.dataLayer = [];
        window.gtagIsTagManager = false;
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('pushes an event through gtag for a Google tag', () => {
        const parameters = { method: 'mail' };

        new AnalyticsEvent().pushEvent('login', parameters, { ecommerce: false });

        expect(window.gtag).toHaveBeenCalledWith('event', 'login', parameters);
        expect(window.dataLayer).toHaveLength(0);
    });

    test('normalizes ecommerce parameters for a Google tag', () => {
        new AnalyticsEvent().pushEvent('view_item', {
            currency: 'EUR',
            value: '19.99',
            items: [{
                item_id: 'product-1',
                price: '19.99',
                quantity: '1',
                item_brand: null,
            }],
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item', {
            currency: 'EUR',
            value: 19.99,
            items: [{
                item_id: 'product-1',
                price: 19.99,
                quantity: 1,
            }],
        });
        expect(window.dataLayer).toHaveLength(0);
    });

    test('pushes an ecommerce event to the GTM data layer', () => {
        window.gtagIsTagManager = true;
        const parameters = {
            currency: 'EUR',
            value: '19.99',
            items: [{
                item_id: 'product-1',
                price: '19.99',
                quantity: '1',
                item_brand: undefined,
            }],
        };

        new AnalyticsEvent().pushEvent('view_item', parameters);

        expect(window.gtag).not.toHaveBeenCalled();
        expect(window.dataLayer).toEqual([
            { ecommerce: null },
            {
                event: 'view_item',
                ecommerce: {
                    currency: 'EUR',
                    value: 19.99,
                    items: [{
                        item_id: 'product-1',
                        price: 19.99,
                        quantity: 1,
                    }],
                },
            },
        ]);
    });

    test('pushes a non-ecommerce event to the GTM data layer', () => {
        window.gtagIsTagManager = true;
        const parameters = { search_term: 'Example' };

        new AnalyticsEvent().pushEvent('search', parameters, { ecommerce: false });

        expect(window.gtag).not.toHaveBeenCalled();
        expect(window.dataLayer).toEqual([{
            event: 'search',
            search_term: 'Example',
        }]);
    });

    // a sum of prices such as 0.1 + 0.2 is 0.30000000000000004
    test('drops the floating point noise of amounts', () => {
        new AnalyticsEvent().pushEvent('view_item_list', {
            value: 0.1 + 0.2,
            items: [{ item_id: 'product-1', price: 961.3899999999999, discount: '2.50', quantity: '2', index: 3 }],
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_item_list', {
            value: 0.3,
            items: [{ item_id: 'product-1', price: 961.39, discount: 2.5, quantity: 2, index: 3 }],
        });
    });

    // currencies such as KWD round to three decimals, and shipping can cost less than a cent
    test('keeps the decimals of the currency', () => {
        new AnalyticsEvent().pushEvent('purchase', {
            value: '1.234',
            shipping: '0.005',
            items: [{ item_id: 'product-1', price: '1.234', quantity: '1' }],
        });

        expect(window.gtag).toHaveBeenCalledWith('event', 'purchase', {
            value: 1.234,
            shipping: 0.005,
            items: [{ item_id: 'product-1', price: 1.234, quantity: 1 }],
        });
    });

    test('keeps a value that is not a number as it is', () => {
        new AnalyticsEvent().pushEvent('purchase', { value: 'not a number', items: [] });

        expect(window.gtag).toHaveBeenCalledWith('event', 'purchase', expect.objectContaining({ value: 'not a number' }));
    });
});
