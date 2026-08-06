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
});
