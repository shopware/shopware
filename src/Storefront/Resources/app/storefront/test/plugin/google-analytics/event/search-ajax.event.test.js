import SearchAjaxEvent from 'src/plugin/google-analytics/events/search-ajax.event';

describe('plugin/google-analytics/events/search-ajax.event', () => {
    let event;

    beforeEach(() => {
        window.gtag = jest.fn();
        window.gtagIsTagManager = false;
        event = new SearchAjaxEvent();
        event.active = true;
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('reports the typed search term without an ecommerce object', () => {
        event._onSearch({ detail: { value: 'shirt' } });

        expect(window.gtag).toHaveBeenCalledWith('event', 'search', { search_term: 'shirt' });
    });

    test('does nothing without consent', () => {
        event.active = false;
        event._onSearch({ detail: { value: 'shirt' } });

        expect(window.gtag).not.toHaveBeenCalled();
    });
});
