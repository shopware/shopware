import LineItemHelper from 'src/plugin/google-analytics/line-item.helper';

describe('plugin/google-analytics/line-item.helper', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    describe('getLineItems', () => {
        test('returns line items with all data attributes', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                    <span class="hidden-line-item"
                        data-id="product-123"
                        data-name="Test Product"
                        data-quantity="2"
                        data-price="99.99"
                        data-brand="Test Brand">
                    </span>
                </div>
            `;

            const lineItems = LineItemHelper.getLineItems();

            expect(lineItems).toHaveLength(1);
            expect(lineItems[0]).toEqual({
                id: 'product-123',
                name: 'Test Product',
                quantity: '2',
                price: '99.99',
                brand: 'Test Brand',
            });
        });

        test('returns line items with categories', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="EUR" data-value="99.99">
                    <span class="hidden-line-item"
                        data-id="product-456"
                        data-name="Categorized Product"
                        data-quantity="1"
                        data-price="49.99"
                        data-brand="Brand"
                        data-category-1="Category 1"
                        data-category-2="Category 2"
                        data-category-3="Category 3">
                    </span>
                </div>
            `;

            const lineItems = LineItemHelper.getLineItems();

            expect(lineItems[0]).toMatchObject({
                item_category: 'Category 1',
                item_category2: 'Category 2',
                item_category3: 'Category 3',
            });
        });

        test('returns multiple line items', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="EUR" data-value="150.00">
                    <span class="hidden-line-item"
                        data-id="product-1"
                        data-name="Product 1"
                        data-quantity="1"
                        data-price="50.00">
                    </span>
                    <span class="hidden-line-item"
                        data-id="product-2"
                        data-name="Product 2"
                        data-quantity="2"
                        data-price="50.00">
                    </span>
                </div>
            `;

            const lineItems = LineItemHelper.getLineItems();

            expect(lineItems).toHaveLength(2);
            expect(lineItems[0].id).toBe('product-1');
            expect(lineItems[1].id).toBe('product-2');
        });
    });

    describe('getAdditionalProperties', () => {
        test('returns currency, shipping, value and tax', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information"
                    data-currency="EUR"
                    data-shipping="4.99"
                    data-value="104.98"
                    data-tax="16.76">
                </div>
            `;

            const props = LineItemHelper.getAdditionalProperties();

            expect(props).toEqual({
                currency: 'EUR',
                shipping: '4.99',
                value: '104.98',
                tax: '16.76',
            });
        });
    });

    describe('getProductData', () => {
        test('returns null when no hidden-line-items-information exists', () => {
            document.body.innerHTML = '';

            const productData = LineItemHelper.getProductData('product-123');

            expect(productData).toBeNull();
        });

        test('returns null when product is not found in line items', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="EUR" data-value="99.99">
                    <span class="hidden-line-item"
                        data-id="product-other"
                        data-name="Other Product">
                    </span>
                </div>
            `;

            const productData = LineItemHelper.getProductData('product-not-found');

            expect(productData).toBeNull();
        });

        test('returns product data for matching product ID', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="EUR" data-value="199.98">
                    <span class="hidden-line-item"
                        data-id="product-123"
                        data-name="Test Product"
                        data-quantity="2"
                        data-price="99.99"
                        data-brand="Test Brand">
                    </span>
                </div>
            `;

            const productData = LineItemHelper.getProductData('product-123');

            expect(productData).toEqual({
                name: 'Test Product',
                brand: 'Test Brand',
                value: '99.99',
                currency: 'EUR',
                categories: {},
            });
        });

        test('returns product data with categories', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="USD" data-value="75.00">
                    <span class="hidden-line-item"
                        data-id="product-456"
                        data-name="Categorized Product"
                        data-price="25.00"
                        data-brand="Brand"
                        data-category-1="Electronics"
                        data-category-2="Phones">
                    </span>
                </div>
            `;

            const productData = LineItemHelper.getProductData('product-456');

            expect(productData).toEqual({
                name: 'Categorized Product',
                brand: 'Brand',
                value: '25.00',
                currency: 'USD',
                categories: {
                    item_category: 'Electronics',
                    item_category2: 'Phones',
                },
            });
        });

        test('finds product among multiple line items', () => {
            document.body.innerHTML = `
                <div class="hidden-line-items-information" data-currency="EUR" data-value="150.00">
                    <span class="hidden-line-item"
                        data-id="product-1"
                        data-name="First Product"
                        data-price="50.00">
                    </span>
                    <span class="hidden-line-item"
                        data-id="product-2"
                        data-name="Second Product"
                        data-price="100.00"
                        data-brand="Second Brand">
                    </span>
                </div>
            `;

            const productData = LineItemHelper.getProductData('product-2');

            expect(productData).toEqual({
                name: 'Second Product',
                brand: 'Second Brand',
                value: '100.00',
                currency: 'EUR',
                categories: {},
            });
        });
    });
});
