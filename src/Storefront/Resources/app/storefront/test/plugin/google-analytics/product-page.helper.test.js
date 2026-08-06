import ProductPageHelper from 'src/plugin/google-analytics/product-page.helper';

describe('plugin/google-analytics/product-page.helper', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    describe('mapCategories', () => {
        test('maps a category path to the GA4 properties', () => {
            expect(ProductPageHelper.mapCategories(['Clothing', 'Shirts', 'Crew'])).toEqual({
                item_category: 'Clothing',
                item_category2: 'Shirts',
                item_category3: 'Crew',
            });
        });

        test('reports at most five levels', () => {
            const path = ['One', 'Two', 'Three', 'Four', 'Five', 'Six'];

            expect(Object.keys(ProductPageHelper.mapCategories(path))).toEqual([
                'item_category',
                'item_category2',
                'item_category3',
                'item_category4',
                'item_category5',
            ]);
        });

        test('returns nothing without a path', () => {
            expect(ProductPageHelper.mapCategories(undefined)).toEqual({});
            expect(ProductPageHelper.mapCategories([])).toEqual({});
        });
    });

    describe('getProductCardData', () => {
        function renderCard(information) {
            document.body.innerHTML = `
                <div class="product-box" data-product-information='${JSON.stringify(information)}'>
                    <div class="product-wishlist-product-123"></div>
                </div>
            `;
        }

        test('returns the categories of the card', () => {
            renderCard({ id: 'product-123', name: 'Shirt', price: 19.99, sku: 'SW10000', categories: ['Clothing', 'Shirts'] });

            expect(ProductPageHelper.getProductCardData('product-123')).toEqual({
                id: 'SW10000',
                name: 'Shirt',
                brand: undefined,
                variant: undefined,
                value: 19.99,
                categories: {
                    item_category: 'Clothing',
                    item_category2: 'Shirts',
                },
            });
        });

        // a page that does not load the category associations still renders product boxes
        test('returns no categories when the card carries none', () => {
            renderCard({ id: 'product-123', name: 'Shirt', price: 19.99, sku: 'SW10000' });

            expect(ProductPageHelper.getProductCardData('product-123').categories).toEqual({});
        });

        test('returns nothing without a card', () => {
            expect(ProductPageHelper.getProductCardData('product-123')).toEqual({});
        });
    });
});
