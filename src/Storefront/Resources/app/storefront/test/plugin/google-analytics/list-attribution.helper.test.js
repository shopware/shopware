import ListAttributionHelper from 'src/plugin/google-analytics/list-attribution.helper';

describe('plugin/google-analytics/list-attribution.helper', () => {
    beforeEach(() => {
        window.sessionStorage.clear();
        ListAttributionHelper.reset();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    describe('getListFromElement', () => {
        test('reads the list of the closest container', () => {
            document.body.innerHTML = `
                <div data-list-id="category-1" data-list-name="Shirts">
                    <div class="product-box"></div>
                </div>
            `;

            expect(ListAttributionHelper.getListFromElement(document.querySelector('.product-box'))).toEqual({
                item_list_id: 'category-1',
                item_list_name: 'Shirts',
            });
        });

        test('omits the name when the page did not provide one', () => {
            document.body.innerHTML = '<div data-list-id="category-1"><div class="product-box"></div></div>';

            expect(ListAttributionHelper.getListFromElement(document.querySelector('.product-box'))).toEqual({
                item_list_id: 'category-1',
                item_list_name: undefined,
            });
        });

        test('returns nothing without a list container', () => {
            document.body.innerHTML = '<div class="product-box"></div>';

            expect(ListAttributionHelper.getListFromElement(document.querySelector('.product-box'))).toEqual({});
            expect(ListAttributionHelper.getListFromElement(null)).toEqual({});
        });
    });

    describe('remember and consume', () => {
        const list = { item_list_id: 'category-1', item_list_name: 'Shirts' };

        test('returns the stored list for the same product', () => {
            ListAttributionHelper.remember('SW10000', list);

            expect(ListAttributionHelper.consume('SW10000')).toEqual(list);
        });

        test('forgets the list after it was consumed', () => {
            ListAttributionHelper.remember('SW10000', list);
            ListAttributionHelper.consume('SW10000');

            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
        });

        test('matches the variant a displayed parent resolves to by its parent id', () => {
            // the listing shows the parent SW10000, the detail page resolves to variant SW10000.1
            ListAttributionHelper.remember('SW10000', list, 'parent-id');

            expect(ListAttributionHelper.consume('SW10000.1', ['variant-id', 'parent-id'])).toEqual(list);
        });

        test('matches a variant card by its own id', () => {
            ListAttributionHelper.remember('SW10000.1', list, 'variant-id');

            expect(ListAttributionHelper.consume('SW10000.1', ['variant-id', 'parent-id'])).toEqual(list);
        });

        test('does not attribute a product of another family', () => {
            ListAttributionHelper.remember('SW10000', list, 'parent-id');

            expect(ListAttributionHelper.consume('SW20000', ['other-id'])).toEqual({});
        });

        test('does not attribute another product', () => {
            ListAttributionHelper.remember('SW10000', list);

            expect(ListAttributionHelper.consume('SW10001')).toEqual({});
        });

        test('returns nothing when nothing was stored', () => {
            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
            expect(ListAttributionHelper.consume(undefined)).toEqual({});
        });

        test('stores nothing without a product or a list', () => {
            ListAttributionHelper.remember('', list);
            ListAttributionHelper.remember('SW10000', {});

            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
        });

        test('survives a corrupted storage value', () => {
            window.sessionStorage.setItem('swGaSelectedItemList', '{invalid');

            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
        });
    });

    describe('getListStart', () => {
        test('reads the offset from the list element', () => {
            document.body.innerHTML = '<div data-list-id="category-1" data-list-start="24"></div>';

            expect(ListAttributionHelper.getListStart(document.querySelector('[data-list-id]'))).toBe(24);
        });

        // AJAX pagination replaces only the inner listing, so the offset lives there
        test('reads the offset from the replaced markup below the list element', () => {
            document.body.innerHTML = `
                <div data-list-id="category-1">
                    <div class="cms-element-product-listing" data-list-start="48"></div>
                </div>
            `;

            expect(ListAttributionHelper.getListStart(document.querySelector('[data-list-id]'))).toBe(48);
        });

        test('counts from zero without an offset', () => {
            document.body.innerHTML = '<div data-list-id="category-1"></div>';

            expect(ListAttributionHelper.getListStart(document.querySelector('[data-list-id]'))).toBe(0);
        });
    });
});
