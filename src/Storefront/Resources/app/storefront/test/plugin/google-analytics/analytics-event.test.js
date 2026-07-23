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

        new AnalyticsEvent().pushEvent('login', parameters);

        expect(window.gtag).toHaveBeenCalledWith('event', 'login', parameters);
        expect(window.dataLayer).toHaveLength(0);
    });

    test('pushes an ecommerce event to the GTM data layer', () => {
        window.gtagIsTagManager = true;
        const parameters = {
            currency: 'EUR',
            value: '19.99',
            items: [{ id: 'product-1' }],
        };

        new AnalyticsEvent().pushEvent('view_item', parameters);

        expect(window.gtag).not.toHaveBeenCalled();
        expect(window.dataLayer).toEqual([
            { ecommerce: null },
            {
                event: 'view_item',
                ecommerce: parameters,
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
