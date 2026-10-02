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
});
