import ProductCategoryHelper from 'src/plugin/google-analytics/product-category.helper';

describe('plugin/google-analytics/product-category.helper', () => {
    const shirts = { item_category: 'Clothing', item_category2: 'Shirts' };
    const mugs = { item_category: 'Kitchen' };

    beforeEach(() => {
        window.localStorage.clear();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    test('returns the remembered categories of a product', () => {
        ProductCategoryHelper.remember('product-1', shirts);
        ProductCategoryHelper.remember('product-2', mugs);

        expect(ProductCategoryHelper.get('product-1')).toEqual(shirts);
        expect(ProductCategoryHelper.get('product-2')).toEqual(mugs);
    });

    // the cart and checkout read them on every page until the order is placed
    test('keeps the categories when they are read', () => {
        ProductCategoryHelper.remember('product-1', shirts);

        ProductCategoryHelper.get('product-1');

        expect(ProductCategoryHelper.get('product-1')).toEqual(shirts);
    });

    test('replaces earlier categories of the same product', () => {
        ProductCategoryHelper.remember('product-1', shirts);
        ProductCategoryHelper.remember('product-1', mugs);

        expect(ProductCategoryHelper.get('product-1')).toEqual(mugs);
        expect(JSON.parse(window.localStorage.getItem('swGaProductCategories'))).toHaveLength(1);
    });

    test('returns no categories for an unknown product', () => {
        ProductCategoryHelper.remember('product-1', shirts);

        expect(ProductCategoryHelper.get('product-2')).toEqual({});
        expect(ProductCategoryHelper.get(null)).toEqual({});
    });

    test('forgets the categories of ordered products', () => {
        ProductCategoryHelper.remember('product-1', shirts);
        ProductCategoryHelper.remember('product-2', mugs);

        ProductCategoryHelper.forget(['product-1']);

        expect(ProductCategoryHelper.get('product-1')).toEqual({});
        expect(ProductCategoryHelper.get('product-2')).toEqual(mugs);

        ProductCategoryHelper.forget(['product-2']);

        expect(window.localStorage.getItem('swGaProductCategories')).toBeNull();
    });

    test('forgets categories after they expired', () => {
        const now = Date.now();
        jest.spyOn(Date, 'now').mockReturnValue(now);
        ProductCategoryHelper.remember('product-1', shirts);

        Date.now.mockReturnValue(now + 31 * 24 * 60 * 60 * 1000);

        expect(ProductCategoryHelper.get('product-1')).toEqual({});
    });

    test('keeps a limited number of products', () => {
        for (let i = 0; i < 105; i++) {
            ProductCategoryHelper.remember(`product-${i}`, shirts);
        }

        expect(ProductCategoryHelper.get('product-4')).toEqual({});
        expect(ProductCategoryHelper.get('product-5')).toEqual(shirts);
        expect(ProductCategoryHelper.get('product-104')).toEqual(shirts);
    });

    test('stores nothing without a product or categories', () => {
        ProductCategoryHelper.remember('', shirts);
        ProductCategoryHelper.remember('product-1', {});

        expect(window.localStorage.getItem('swGaProductCategories')).toBeNull();
    });

    test('treats an unreadable storage entry as empty', () => {
        window.localStorage.setItem('swGaProductCategories', '{broken');

        expect(ProductCategoryHelper.get('product-1')).toEqual({});

        ProductCategoryHelper.remember('product-1', shirts);
        expect(ProductCategoryHelper.get('product-1')).toEqual(shirts);
    });
});
