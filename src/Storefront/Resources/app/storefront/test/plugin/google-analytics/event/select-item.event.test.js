import ListAttributionHelper from 'src/plugin/google-analytics/list-attribution.helper';
import SelectItemEvent from 'src/plugin/google-analytics/events/select-item.event';

describe('plugin/google-analytics/events/select-item.event', () => {
    let selectItemEvent;

    beforeEach(() => {
        window.gtag = jest.fn();
        window.sessionStorage.clear();

        selectItemEvent = new SelectItemEvent();
        selectItemEvent.execute();
    });

    afterEach(() => {
        // the listener is delegated to `document`, which outlives a single test
        document.removeEventListener('click', selectItemEvent._boundOnClick);
        document.body.innerHTML = '';
        jest.clearAllMocks();
    });

    function renderListing(products, listAttributes = 'data-list-id="category-1" data-list-name="Shirts"') {
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
            'item_list_id': 'category-1',
            'item_list_name': 'Shirts',
            'items': [{
                'item_id': 'SW10001',
                'item_name': 'Mug',
                'price': 4.99,
                'index': 1,
            }],
        });
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

    test('ignores clicks outside a product box', () => {
        document.body.innerHTML = '<div class="not-a-product">Text</div>';

        document.querySelector('.not-a-product').click();

        expect(window.gtag).not.toHaveBeenCalled();
    });

    test('fires without a list identity, for example in a slider', () => {
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

    test('remembers the list so the detail page can report it', () => {
        renderListing([shirt]);

        document.querySelector('.product-name').click();

        expect(ListAttributionHelper.consume('SW10000')).toEqual({
            item_list_id: 'category-1',
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
});
