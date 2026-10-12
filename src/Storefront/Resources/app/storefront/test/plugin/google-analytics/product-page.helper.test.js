import Feature from 'src/helper/feature.helper';
import ProductPageHelper from 'src/plugin/google-analytics/product-page.helper';

describe('plugin/google-analytics/product-page.helper', () => {
    beforeEach(() => {
        Feature.init({ JSON_LD_DATA: false });
    });

    afterEach(() => {
        document.body.innerHTML = '';
        delete window.currencyIsoCode;
    });

    test('gets SKU, brand, currency and amount value from product detail markup', () => {
        document.body.innerHTML = `
            <span itemprop="sku">
                product-123
            </span>
            <div itemprop="brand">
                <meta itemprop="name" content="Test Brand">
            </div>
            <meta property="product:price:currency" content="EUR">
            <meta property="product:price:amount" content="99.99">
        `;

        expect(ProductPageHelper.getSku()).toBe('product-123');
        expect(ProductPageHelper.getBrand()).toBe('Test Brand');
        expect(ProductPageHelper.getCurrency()).toBe('EUR');
        expect(ProductPageHelper.getValue()).toBe('99.99');
    });

    test('gets currency from the global variable when the currency meta tag is missing', () => {
        window.currencyIsoCode = 'USD';

        expect(ProductPageHelper.getCurrency()).toBe('USD');
    });

    test('gets SKU, brand, currency and amount value from JSON-LD product data', () => {
        Feature.init({ JSON_LD_DATA: true });

        const productData = {
            '@context': 'https://schema.org',
            '@type': 'Product',
            name: 'Test Product',
            sku: 'product-456',
            brand: {
                '@type': 'Brand',
                name: 'JSON-LD Brand',
            },
            offers: {
                '@type': 'Offer',
                priceCurrency: 'USD',
                price: '49.99',
            },
        };

        document.body.innerHTML = `<script type="application/ld+json">${JSON.stringify(productData)}</script>`;

        expect(ProductPageHelper.getSku()).toBe('product-456');
        expect(ProductPageHelper.getBrand()).toBe('JSON-LD Brand');
        expect(ProductPageHelper.getCurrency()).toBe('USD');
        expect(ProductPageHelper.getValue()).toBe('49.99');
    });

    test('gets all variant product detail data from JSON-LD with one lookup', () => {
        Feature.init({ JSON_LD_DATA: true });
        const productData = {
            '@type': 'ProductGroup',
            name: 'Product Group',
            brand: { name: 'Group Brand' },
            hasVariant: [{
                '@type': 'Product',
                name: 'Variant Product',
                sku: 'product-variant',
                offers: { priceCurrency: 'USD', lowPrice: '49.99' },
            }],
        };

        document.body.innerHTML = `<script type="application/ld+json">${JSON.stringify(productData)}</script>`;
        const getJsonLdProductData = jest.spyOn(ProductPageHelper, 'getJsonLdProductData');

        expect(ProductPageHelper.getProductDetailData()).toEqual({
            id: 'product-variant',
            name: 'Variant Product',
            brand: 'Group Brand',
            currency: 'USD',
            value: '49.99',
        });
        expect(getJsonLdProductData).toHaveBeenCalledTimes(1);
    });

    test('gets product data from the product JSON-LD script when multiple scripts exist', () => {
        Feature.init({ JSON_LD_DATA: true });

        const breadcrumbData = {
            '@context': 'https://schema.org',
            '@type': 'BreadcrumbList',
            itemListElement: [],
        };
        const productData = {
            '@context': 'https://schema.org',
            '@type': 'Product',
            name: 'Test Product',
            sku: 'product-789',
            brand: { name: 'Product Brand' },
            offers: { priceCurrency: 'EUR', price: '19.99' },
        };

        document.body.innerHTML = `
            <script type="application/ld+json">${JSON.stringify(breadcrumbData)}</script>
            <script type="application/ld+json">${JSON.stringify(productData)}</script>
        `;

        expect(ProductPageHelper.getSku()).toBe('product-789');
        expect(ProductPageHelper.getBrand()).toBe('Product Brand');
        expect(ProductPageHelper.getCurrency()).toBe('EUR');
        expect(ProductPageHelper.getValue()).toBe('19.99');
    });

    test('gets product data from a product entity inside JSON-LD graph', () => {
        Feature.init({ JSON_LD_DATA: true });

        const structuredData = {
            '@context': 'https://schema.org',
            '@graph': [
                {
                    '@type': 'BreadcrumbList',
                    itemListElement: [],
                },
                {
                    '@type': 'Product',
                    name: 'Graph Product',
                    sku: 'product-graph',
                    brand: { name: 'Graph Brand' },
                    offers: { priceCurrency: 'GBP', price: '29.99' },
                },
            ],
        };

        document.body.innerHTML = `<script type="application/ld+json">${JSON.stringify(structuredData)}</script>`;

        expect(ProductPageHelper.getSku()).toBe('product-graph');
        expect(ProductPageHelper.getBrand()).toBe('Graph Brand');
        expect(ProductPageHelper.getCurrency()).toBe('GBP');
        expect(ProductPageHelper.getValue()).toBe('29.99');
    });

    test('gets currency from the global variable when JSON-LD currency is missing', () => {
        Feature.init({ JSON_LD_DATA: true });

        window.currencyIsoCode = 'GBP';
        document.body.innerHTML = '<script type="application/ld+json">{"@type":"Product"}</script>';

        expect(ProductPageHelper.getCurrency()).toBe('GBP');
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

        test('returns the data of the card', () => {
            renderCard({ id: 'product-123', name: 'Shirt', price: 19.99, sku: 'SW10000' });

            expect(ProductPageHelper.getProductCardData('product-123')).toEqual({
                id: 'SW10000',
                name: 'Shirt',
                brand: undefined,
                variant: undefined,
                value: 19.99,
            });
        });

        test('returns nothing without a card', () => {
            expect(ProductPageHelper.getProductCardData('product-123')).toEqual({});
        });
    });

    describe('resolveCategories', () => {
        const breadcrumb = `
            <nav aria-label="breadcrumb">
                <span class="breadcrumb-title">Listing</span>
            </nav>
        `;

        beforeEach(() => {
            window.router = { 'frontend.analytics.product-categories': '/widgets/analytics/product-categories' };
            window.activeRoute = 'frontend.navigation.page';
            document.body.innerHTML = breadcrumb;
        });

        afterEach(() => {
            delete window.router;
            delete window.activeRoute;
            delete global.fetch;
        });

        test('requests the path of the product instead of using the breadcrumb of a listing', async () => {
            global.fetch = jest.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(['Damen', 'Schuhe']) });

            await expect(ProductPageHelper.resolveCategories('product-123')).resolves.toEqual({
                item_category: 'Damen',
                item_category2: 'Schuhe',
            });
            expect(global.fetch).toHaveBeenCalledWith(
                '/widgets/analytics/product-categories?productId=product-123',
                expect.objectContaining({ headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
            );
        });

        test('reports no category for a product without one, rather than the listing', async () => {
            global.fetch = jest.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) });

            await expect(ProductPageHelper.resolveCategories('product-123')).resolves.toEqual({});
        });

        test('uses the breadcrumb on the product detail page without a request', async () => {
            window.activeRoute = 'frontend.detail.page';
            global.fetch = jest.fn();

            await expect(ProductPageHelper.resolveCategories('product-123')).resolves.toEqual({ item_category: 'Listing' });
            expect(global.fetch).not.toHaveBeenCalled();
        });

        test.each([
            ['the request fails', () => Promise.reject(new Error('offline'))],
            ['the response is an error', () => Promise.resolve({ ok: false })],
        ])('falls back to the breadcrumb when %s', async (label, response) => {
            global.fetch = jest.fn(response);

            await expect(ProductPageHelper.resolveCategories('product-123')).resolves.toEqual({ item_category: 'Listing' });
        });

        test('requests the path of a product box on the product detail page', async () => {
            window.activeRoute = 'frontend.detail.page';
            document.body.innerHTML = `${breadcrumb}
                <div class="product-box" data-product-information='{ "id": "slider-id" }'>
                    <button class="product-wishlist-slider-id"></button>
                </div>
            `;
            global.fetch = jest.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(['Damen']) });

            await expect(ProductPageHelper.resolveCategories('slider-id')).resolves.toEqual({ item_category: 'Damen' });
            expect(global.fetch).toHaveBeenCalled();
        });
    });

    describe('on a product detail page with a product box', () => {
        beforeEach(() => {
            document.body.innerHTML = `
                <h1 class="product-detail-name">Main Product</h1>
                <span itemprop="sku">SW-MAIN</span>
                <meta property="product:price:currency" content="EUR">
                <meta property="product:price:amount" content="50">
                <div class="product-detail-buy">
                    <form class="buy-widget"></form>
                    <button class="product-wishlist-main-id"></button>
                </div>
                <div class="product-box" data-product-information='{ "id": "slider-id", "name": "Slider Product", "price": 9.99, "sku": "SW-SLIDER" }'>
                    <button class="product-wishlist-slider-id"></button>
                    <form class="buy-widget"></form>
                </div>
            `;
        });

        test('reports the product of a cross selling box instead of the page product', () => {
            expect(ProductPageHelper.getProductData('slider-id')).toEqual(expect.objectContaining({
                id: 'SW-SLIDER',
                name: 'Slider Product',
                value: 9.99,
                currency: 'EUR',
            }));
        });

        test('finds the box through the element the interaction started from', () => {
            const form = document.querySelector('.product-box form');

            expect(ProductPageHelper.getProductData('unknown-id', form)).toEqual(expect.objectContaining({
                id: 'SW-SLIDER',
            }));
        });

        test('reports no breadcrumb categories for the product of a box', () => {
            window.activeRoute = 'frontend.detail.page';
            document.body.insertAdjacentHTML('beforeend', '<nav aria-label="breadcrumb"><span class="breadcrumb-title">Main Category</span></nav>');

            expect(ProductPageHelper.getCategoriesFor('slider-id')).toEqual({});
            expect(ProductPageHelper.getCategoriesFor('main-id', document.querySelector('.product-detail-buy form')))
                .toEqual({ item_category: 'Main Category' });

            delete window.activeRoute;
        });

        test('keeps reporting the page product for the buy widget', () => {
            const form = document.querySelector('.product-detail-buy form');

            expect(ProductPageHelper.getProductData('main-id', form)).toEqual(expect.objectContaining({
                id: 'SW-MAIN',
                name: 'Main Product',
            }));
        });
    });
});
