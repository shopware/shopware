import ListAttributionHelper from 'src/plugin/google-analytics/list-attribution.helper';

describe('plugin/google-analytics/list-attribution.helper', () => {
    const shirts = { item_list_id: 'category:1', item_list_name: 'Shirts' };
    const search = { item_list_id: 'search', item_list_name: 'Search results' };

    beforeEach(() => {
        window.localStorage.clear();
    });

    afterEach(() => {
        jest.restoreAllMocks();
        document.body.innerHTML = '';
    });

    describe('getListFromElement', () => {
        test('reads the list a product element belongs to', () => {
            document.body.innerHTML = `
                <div data-list-id="category:1" data-list-name="Shirts">
                    <div class="product-box"></div>
                </div>
            `;

            expect(ListAttributionHelper.getListFromElement(document.querySelector('.product-box'))).toEqual(shirts);
        });

        test('prefers an identified list over the shopping experience element around it', () => {
            document.body.innerHTML = `
                <div data-cms-element-id="element-1">
                    <div data-list-id="slider:1"><div class="product-box"></div></div>
                </div>
            `;

            expect(ListAttributionHelper.getListFromElement(document.querySelector('.product-box'))).toEqual({
                item_list_id: 'slider:1',
                item_list_name: undefined,
            });
        });

        test('falls back to the shopping experience element of an extension', () => {
            document.body.innerHTML = '<div data-cms-element-id="element-1"><div class="acme-teaser"></div></div>';

            expect(ListAttributionHelper.getListFromElement(document.querySelector('.acme-teaser'))).toEqual({
                item_list_id: 'cms:element-1',
            });
        });

        test('returns no list outside of any list', () => {
            document.body.innerHTML = '<div class="product-box"></div>';

            expect(ListAttributionHelper.getListFromElement(document.querySelector('.product-box'))).toEqual({});
            expect(ListAttributionHelper.getListFromElement(null)).toEqual({});
        });
    });

    describe('getListStart', () => {
        test('reads the start from the list or the element below it', () => {
            document.body.innerHTML = `
                <div id="own" data-list-start="12"></div>
                <div id="nested"><div class="js-listing-wrapper" data-list-start="24"></div></div>
            `;

            expect(ListAttributionHelper.getListStart(document.getElementById('own'))).toBe(12);
            expect(ListAttributionHelper.getListStart(document.getElementById('nested'))).toBe(24);
        });

        test.each([
            ['no attribute', '<div id="list"></div>'],
            ['a value that is no number', '<div id="list" data-list-start="abc"></div>'],
            ['a negative value', '<div id="list" data-list-start="-4"></div>'],
        ])('starts at zero with %s', (label, markup) => {
            document.body.innerHTML = markup;

            expect(ListAttributionHelper.getListStart(document.getElementById('list'))).toBe(0);
        });

        test('starts at zero without a list', () => {
            expect(ListAttributionHelper.getListStart(null)).toBe(0);
        });
    });

    describe('remember and consume', () => {
        test('returns the list of a selected product once', () => {
            ListAttributionHelper.remember('SW10000', shirts, 'product-1');

            expect(ListAttributionHelper.consume('SW10000')).toEqual(shirts);
            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
        });

        test('keeps the lists of several products opened in other tabs', () => {
            ListAttributionHelper.remember('SW10000', shirts, 'product-1');
            ListAttributionHelper.remember('SW10001', search, 'product-2');

            expect(ListAttributionHelper.consume('SW10001')).toEqual(search);
            expect(ListAttributionHelper.consume('SW10000')).toEqual(shirts);
        });

        test('replaces an earlier selection of the same product', () => {
            ListAttributionHelper.remember('SW10000', shirts, 'product-1');
            ListAttributionHelper.remember('SW10000', search, 'product-1');

            expect(ListAttributionHelper.consume('SW10000')).toEqual(search);
            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
        });

        // a listing displays the parent, the detail page resolves to one of its variants
        test('matches the variant of a selected parent by its parent id', () => {
            ListAttributionHelper.remember('SW10000', shirts, 'parent-id');

            expect(ListAttributionHelper.consume('SW10000.1', ['variant-id', 'parent-id'])).toEqual(shirts);
        });

        test('does not attribute a product that was not selected', () => {
            ListAttributionHelper.remember('SW10000', shirts, 'product-1');

            expect(ListAttributionHelper.consume('SW10001', ['product-2'])).toEqual({});
            expect(ListAttributionHelper.consume('SW10000')).toEqual(shirts);
        });

        test('forgets a selection after it expired', () => {
            const now = Date.now();
            jest.spyOn(Date, 'now').mockReturnValue(now);
            ListAttributionHelper.remember('SW10000', shirts, 'product-1');

            Date.now.mockReturnValue(now + 31 * 60 * 1000);

            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
        });

        test('keeps a limited number of selections', () => {
            for (let i = 0; i < 25; i++) {
                ListAttributionHelper.remember(`SW${i}`, shirts, `product-${i}`);
            }

            expect(ListAttributionHelper.consume('SW4')).toEqual({});
            expect(ListAttributionHelper.consume('SW5')).toEqual(shirts);
            expect(ListAttributionHelper.consume('SW24')).toEqual(shirts);
        });

        test('does not store a selection without a product number or list', () => {
            ListAttributionHelper.remember('', shirts);
            ListAttributionHelper.remember('SW10000', {});

            expect(window.localStorage.getItem('swGaSelectedItemLists')).toBeNull();
        });

        test('removes the storage entry once every selection was consumed', () => {
            ListAttributionHelper.remember('SW10000', shirts, 'product-1');
            ListAttributionHelper.consume('SW10000');

            expect(window.localStorage.getItem('swGaSelectedItemLists')).toBeNull();
        });

        test('treats an unreadable storage entry as empty', () => {
            window.localStorage.setItem('swGaSelectedItemLists', '{broken');

            expect(ListAttributionHelper.consume('SW10000')).toEqual({});

            ListAttributionHelper.remember('SW10000', shirts);
            expect(ListAttributionHelper.consume('SW10000')).toEqual(shirts);
        });
    });
});
