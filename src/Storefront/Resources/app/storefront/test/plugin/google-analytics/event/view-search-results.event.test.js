import ViewSearchResults from 'src/plugin/google-analytics/events/view-search-results';

describe('plugin/google-analytics/events/view-search-results.event', () => {
    beforeEach(() => {
        window.gtag = jest.fn();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    test('supports returns true on search page', () => {
        expect(new ViewSearchResults().supports('', '', 'frontend.search.page')).toBe(true);
    });

    test('supports returns false on other pages', () => {
        expect(new ViewSearchResults().supports('', '', 'frontend.home.page')).toBe(false);
        expect(new ViewSearchResults().supports('', '', 'frontend.detail.page')).toBe(false);
    });

    test('fires view_search_results event with search term', () => {
        document.body.innerHTML = `
            <input class="header-search-input" value="test search term">
        `;

        new ViewSearchResults().execute();

        expect(window.gtag).toHaveBeenCalledWith('event', 'view_search_results', {
            'search_term': 'test search term',
        });
    });
});

