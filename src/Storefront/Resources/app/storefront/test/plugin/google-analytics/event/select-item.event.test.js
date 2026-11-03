import ListAttributionHelper from 'src/plugin/google-analytics/list-attribution.helper';
import SelectItemEvent from 'src/plugin/google-analytics/events/select-item.event';

describe('plugin/google-analytics/events/select-item.event', () => {
    let selectItemEvent;

    beforeEach(() => {
        window.gtag = jest.fn();
        window.localStorage.clear();

        selectItemEvent = new SelectItemEvent();
        selectItemEvent.execute();
    });

    afterEach(() => {
        // the listener is delegated to `document`, which outlives a single test
        document.removeEventListener('click', selectItemEvent._boundOnClick);
        document.removeEventListener('auxclick', selectItemEvent._boundOnClick);
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    function renderListing(products, listAttributes = 'data-list-id="category:1" data-list-name="Shirts"') {
        document.body.innerHTML = `
            <div class="cms-element-product-listing-wrapper" ${listAttributes}>
                ${products.map(product => `
                    <div class="product-box" data-product-information='${JSON.stringify(product)}'>
                        <a href="/detail" class="product-name stretched-link">${product.name}</a>
                        <form action="/checkout/line-item/add"><button type="button" class="btn-buy">Add to cart</button></form>
                    </div>
                `).join('')}
            </div>
        `;
    }

    const shirt = { id: 'product-1', name: 'Shirt', brand: 'Acme', price: 19.99, sku: 'SW10000', variant: 'Red, L' };
    const mug = { id: 'product-2', name: 'Mug', price: 4.99, sku: 'SW10001' };

    test('supports every route', () => {
        expect(selectItemEvent.supports()).toBe(true);
    });

    test('fires select_item with the list and the position of the product', () => {
        renderListing([shirt, mug]);

        document.querySelectorAll('.product-name')[1].click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'select_item', {
            'item_list_id': 'category:1',
            'item_list_name': 'Shirts',
            'items': [{
                'item_id': 'SW10001',
                'item_name': 'Mug',
                'price': 4.99,
                'index': 1,
            }],
        });
    });

    test('reports the search term of a product selected from the search result', () => {
        renderListing([shirt], 'data-list-id="search" data-list-name="Search results" data-list-search-term="shirt"');

        document.querySelector('.product-name').click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'select_item', expect.objectContaining({
            'item_list_id': 'search',
            'search_term': 'shirt',
        }));
    });

    test('reports the brand and the variant of the product', () => {
        renderListing([shirt]);

        document.querySelector('.product-name').click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'select_item', expect.objectContaining({
            'items': [expect.objectContaining({
                'item_brand': 'Acme',
                'item_variant': 'Red, L',
            })],
        }));
    });

    test('does not report a buy button click as a selection', () => {
        renderListing([shirt]);

        document.querySelector('.btn-buy').click();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('ignores clicks outside a product element', () => {
        document.body.innerHTML = '<a href="/somewhere" class="not-a-product">Text</a>';

        document.querySelector('.not-a-product').click();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('fires without a list identity outside any list', () => {
        renderListing([shirt], '');

        document.querySelector('.product-name').click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'select_item', {
            'items': [{
                'item_id': 'SW10000',
                'item_name': 'Shirt',
                'item_brand': 'Acme',
                'item_variant': 'Red, L',
                'price': 19.99,
            }],
        });
    });

    // an extension renders its own product elements in its own shopping experience element
    test('attributes a product of an extension element to that element', () => {
        document.body.innerHTML = `
            <div data-cms-element-id="element-1">
                <article class="acme-teaser" data-product-information='${JSON.stringify(mug)}'>
                    <a href="/detail" class="acme-teaser-link">Mug</a>
                </article>
                <article class="acme-teaser" data-product-information='${JSON.stringify(shirt)}'>
                    <a href="/detail" class="acme-teaser-link">Shirt</a>
                </article>
            </div>
        `;

        document.querySelectorAll('.acme-teaser-link')[1].click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'select_item', expect.objectContaining({
            'item_list_id': 'cms:element-1',
            'items': [expect.objectContaining({ 'item_id': 'SW10000', 'index': 1 })],
        }));
    });

    test('remembers the list so the detail page can report it', () => {
        renderListing([shirt]);

        document.querySelector('.product-name').click();

        expect(ListAttributionHelper.consume('SW10000')).toEqual({
            item_list_id: 'category:1',
            item_list_name: 'Shirts',
        });
    });

    test('does not fire when the event is disabled', () => {
        renderListing([shirt]);
        selectItemEvent.disable();

        document.querySelector('.product-name').click();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    // the listing plugin replaces its subtree on every filter and pagination
    test('keeps working after the listing was re-rendered', () => {
        renderListing([shirt]);
        renderListing([mug]);

        document.querySelector('.product-name').click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'select_item', expect.objectContaining({
            'items': [expect.objectContaining({ 'item_id': 'SW10001' })],
        }));
    });

    test('does not fire for a click inside the card that follows no link', () => {
        renderListing([shirt]);
        document.querySelector('.product-box').insertAdjacentHTML(
            'beforeend',
            '<div class="product-variant-characteristics">Red, L</div>',
        );

        document.querySelector('.product-variant-characteristics').click();
        document.querySelector('.product-box').click();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('counts the index across the pages of a paginated listing', () => {
        renderListing([shirt, mug], 'data-list-id="category:1" data-list-name="Shirts" data-list-start="24"');

        document.querySelectorAll('.product-name')[1].click();

        expect(window.gtag).toHaveBeenCalledWith('event', 'select_item', expect.objectContaining({
            'items': [expect.objectContaining({ 'index': 25 })],
        }));
    });

    test.each([
        ['a Ctrl click', { ctrlKey: true }],
        ['a Cmd click', { metaKey: true }],
        ['a Shift click', { shiftKey: true }],
    ])('reports %s and keeps the list for the tab it opens', (label, modifiers) => {
        renderListing([shirt]);

        document.querySelector('.product-name').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, ...modifiers }));

        expect(window.gtag).toHaveBeenCalledWith('event', 'select_item', expect.anything());
        expect(ListAttributionHelper.consume('SW10000')).toEqual({ item_list_id: 'category:1', item_list_name: 'Shirts' });
    });

    test('reports a middle click, which opens the product in another tab', () => {
        renderListing([shirt]);

        document.querySelector('.product-name').dispatchEvent(new MouseEvent('auxclick', { bubbles: true, cancelable: true, button: 1 }));

        expect(window.gtag).toHaveBeenCalledWith('event', 'select_item', expect.objectContaining({
            'item_list_id': 'category:1',
        }));
        expect(ListAttributionHelper.consume('SW10000')).toEqual({ item_list_id: 'category:1', item_list_name: 'Shirts' });
    });

    test('ignores a right click, which opens the context menu', () => {
        renderListing([shirt]);

        document.querySelector('.product-name').dispatchEvent(new MouseEvent('auxclick', { bubbles: true, cancelable: true, button: 2 }));

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test.each([
        ['an element without product information', 'class="product-box"'],
        ['unreadable product information', 'data-product-information=\'{"broken\''],
        ['product information without a product number or id', 'data-product-information=\'{"name":"Shirt"}\''],
    ])('reports nothing for %s', (label, attribute) => {
        document.body.innerHTML = `
            <div data-list-id="category:1">
                <div ${attribute}><a href="/detail" class="product-name">Shirt</a></div>
            </div>
        `;

        document.querySelector('.product-name').click();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('reports no position for a product element that is its own list', () => {
        document.body.innerHTML = `
            <div class="product-box" data-list-id="slider:1" data-product-information='${JSON.stringify(shirt)}'>
                <a href="/detail" class="product-name">Shirt</a>
            </div>
        `;

        document.querySelector('.product-name').click();

        const item = window.gtag.mock.calls[0][2].items[0];
        expect(item.item_id).toBe('SW10000');
        expect(item).not.toHaveProperty('index');
    });
});
