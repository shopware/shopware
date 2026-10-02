import ListAttributionHelper from 'src/plugin/google-analytics/list-attribution.helper';

describe('plugin/google-analytics/list-attribution.helper', () => {
    beforeEach(() => {
        window.sessionStorage.clear();
        window.localStorage.clear();
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

    describe('handOver', () => {
        const list = { item_list_id: 'category-1', item_list_name: 'Shirts' };

        // the tab the product is opened in has the page that stored the handover as its referrer
        function openedFrom(url) {
            Object.defineProperty(document, 'referrer', { value: url, configurable: true });
        }

        afterEach(() => {
            jest.useRealTimers();
            openedFrom('');
            window.history.replaceState(null, '', '/');
        });

        test('hands the list over to the tab opened from this page', () => {
            ListAttributionHelper.handOver('SW10000', list, 'product-1');
            openedFrom(window.location.href);

            expect(window.sessionStorage.getItem('swGaSelectedItemList')).toBeNull();
            expect(ListAttributionHelper.consume('SW10000')).toEqual(list);
            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
        });

        test('matches the referrer of a page whose address has a fragment', () => {
            window.history.replaceState(null, '', '/shirts#filter');
            ListAttributionHelper.handOver('SW10000', list, 'product-1');
            openedFrom('http://localhost/shirts');

            expect(ListAttributionHelper.consume('SW10000')).toEqual(list);
        });

        test('is not taken by a direct visit of the product', () => {
            ListAttributionHelper.handOver('SW10000', list, 'product-1');
            openedFrom('');

            expect(ListAttributionHelper.consume('SW10000')).toEqual({});

            openedFrom(window.location.href);
            expect(ListAttributionHelper.consume('SW10000')).toEqual(list);
        });

        test('is not taken by a tab opened from another page', () => {
            ListAttributionHelper.handOver('SW10000', list, 'product-1');
            openedFrom('http://localhost/other-page');

            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
        });

        test('keeps the same product opened from the lists of two pages apart', () => {
            window.history.replaceState(null, '', '/shirts');
            ListAttributionHelper.handOver('SW10000', list, 'product-1');
            window.history.replaceState(null, '', '/search');
            ListAttributionHelper.handOver('SW10000', { item_list_id: 'search' }, 'product-1');

            openedFrom('http://localhost/shirts');
            expect(ListAttributionHelper.consume('SW10000')).toEqual(list);

            openedFrom('http://localhost/search');
            expect(ListAttributionHelper.consume('SW10000')).toEqual({ item_list_id: 'search' });
        });

        test('keeps the same product opened from two lists of one page, in the order it was opened', () => {
            ListAttributionHelper.handOver('SW10000', { item_list_id: 'cross-selling-1' }, 'product-1');
            ListAttributionHelper.handOver('SW10000', { item_list_id: 'cross-selling-2' }, 'product-1');
            openedFrom(window.location.href);

            expect(ListAttributionHelper.consume('SW10000')).toEqual({ item_list_id: 'cross-selling-1' });
            expect(ListAttributionHelper.consume('SW10000')).toEqual({ item_list_id: 'cross-selling-2' });
        });

        test('keeps a handover for every tab the same product of the same list is opened in', () => {
            ListAttributionHelper.handOver('SW10000', list, 'product-1');
            ListAttributionHelper.handOver('SW10000', list, 'product-1');
            openedFrom(window.location.href);

            expect(ListAttributionHelper.consume('SW10000')).toEqual(list);
            expect(ListAttributionHelper.consume('SW10000')).toEqual(list);
            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
        });

        test('keeps the handovers of several products opened at once', () => {
            ListAttributionHelper.handOver('SW10000', list, 'product-1');
            ListAttributionHelper.handOver('SW10001', { item_list_id: 'search' }, 'product-2');
            openedFrom(window.location.href);

            expect(ListAttributionHelper.consume(undefined, ['product-2'])).toEqual({ item_list_id: 'search' });
            expect(ListAttributionHelper.consume('SW10000')).toEqual(list);
        });

        test('prefers the selection of this tab over a handover', () => {
            ListAttributionHelper.handOver('SW10000', { item_list_id: 'search' }, 'product-1');
            ListAttributionHelper.remember('SW10000', list, 'product-1');
            openedFrom(window.location.href);

            expect(ListAttributionHelper.consume('SW10000')).toEqual(list);
        });

        test('forgets a handover after a minute', () => {
            jest.useFakeTimers();
            ListAttributionHelper.handOver('SW10000', list, 'product-1');
            openedFrom(window.location.href);

            jest.advanceTimersByTime(60001);

            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
        });

        test('keeps at most ten handovers', () => {
            for (let i = 0; i < 11; i++) {
                ListAttributionHelper.handOver(`SW1000${i}`, list);
            }
            openedFrom(window.location.href);

            expect(JSON.parse(window.localStorage.getItem('swGaSelectedItemListHandover'))).toHaveLength(10);
            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
            expect(ListAttributionHelper.consume('SW100010')).toEqual(list);
        });

        test('survives a corrupted handover value', () => {
            window.localStorage.setItem('swGaSelectedItemListHandover', '{invalid');
            openedFrom(window.location.href);

            expect(ListAttributionHelper.consume('SW10000')).toEqual({});
        });
    });

    describe('getListStart', () => {
        test('reads the offset from the list element', () => {
            document.body.innerHTML = '<div data-list-id="category-1" data-list-start="24"></div>';

            expect(ListAttributionHelper.getListStart(document.querySelector('[data-list-id]'))).toBe(24);
        });

        // AJAX pagination only swaps the inner markup of the listing, so the offset lives inside it
        test('reads the offset from the replaced markup below the list element', () => {
            document.body.innerHTML = `
                <div data-list-id="category-1">
                    <div class="cms-element-product-listing"><div class="cms-listing-row" data-list-start="48"></div></div>
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
