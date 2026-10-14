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

    describe('graduated prices', () => {
        const tiers = JSON.stringify([
            { quantity: 10, price: 20 },
            { quantity: 50, price: 15 },
            { quantity: 51, price: 10 },
        ]);

        beforeEach(() => {
            document.body.innerHTML = `
                <div class="product-detail-buy" data-product-prices='${tiers}'>
                    <form class="buy-widget"></form>
                </div>
            `;
        });

        test.each([
            [1, 20],
            [10, 20],
            [11, 15],
            [50, 15],
            [51, 10],
            [500, 10],
        ])('charges the tier of quantity %i', (quantity, price) => {
            expect(ProductPageHelper.getGraduatedPrice(document.querySelector('.buy-widget'), quantity)).toBe(price);
        });

        test('returns undefined without graduated prices', () => {
            document.body.innerHTML = '<form class="buy-widget"></form>';

            expect(ProductPageHelper.getGraduatedPrice(document.querySelector('.buy-widget'), 5)).toBeUndefined();
        });

        test('charges the first tier without a quantity', () => {
            expect(ProductPageHelper.getGraduatedPrice(document.querySelector('.buy-widget'), undefined)).toBe(20);
        });

        test('returns undefined for an empty list of tiers', () => {
            document.body.innerHTML = '<div data-product-prices="[]"><form class="buy-widget"></form></div>';

            expect(ProductPageHelper.getGraduatedPrice(document.querySelector('.buy-widget'), 5)).toBeUndefined();
        });

        test('returns undefined for invalid price data', () => {
            document.body.innerHTML = '<div data-product-prices="not json"><form class="buy-widget"></form></div>';

            expect(ProductPageHelper.getGraduatedPrice(document.querySelector('.buy-widget'), 5)).toBeUndefined();
        });
    });

    describe('variants', () => {
        test('reads the selected options of the detail page', () => {
            document.body.innerHTML = `
                <h1 class="product-detail-name">Shirt</h1>
                <div class="product-detail-buy" data-product-variant="Red, L"></div>
            `;

            expect(ProductPageHelper.getVariant()).toBe('Red, L');
            expect(ProductPageHelper.getProductDetailData().variant).toBe('Red, L');
        });

        test('reports no variant for a product without options', () => {
            document.body.innerHTML = '<div class="product-detail-buy" data-product-variant=""></div>';

            expect(ProductPageHelper.getVariant()).toBeUndefined();
        });

        test('reads the variant of a product element', () => {
            document.body.innerHTML = `
                <div class="card product-box" data-product-information='{"id":"product-1","variant":"Blue, M"}'>
                    <button class="buy"></button>
                </div>
            `;

            expect(ProductPageHelper.getProductCardData('product-1', document.querySelector('.buy')).variant).toBe('Blue, M');
        });
    });

    describe('product elements', () => {
        const breadcrumb = `
            <nav aria-label="breadcrumb">
                <span class="breadcrumb-title">Clothing</span>
                <span class="breadcrumb-title">Shirts</span>
            </nav>
        `;

        beforeEach(() => {
            window.activeRoute = 'frontend.navigation.page';
        });

        afterEach(() => {
            delete window.activeRoute;
        });

        test('finds a product element by its data attribute, without the product box class', () => {
            document.body.innerHTML = `
                <article class="custom-teaser" data-product-information='{"id":"product-1","sku":"SW1"}'>
                    <button class="custom-buy">Buy</button>
                </article>
            `;

            const card = ProductPageHelper.getProductCard('product-1', document.querySelector('.custom-buy'));

            expect(card).toBe(document.querySelector('.custom-teaser'));
        });

        test('finds a product element through its wishlist button', () => {
            document.body.innerHTML = `
                <div class="card product-box" data-product-information='{"id":"product-1"}'>
                    <button class="product-wishlist-product-1"></button>
                </div>
            `;

            expect(ProductPageHelper.getProductCard('product-1')).toBe(document.querySelector('.product-box'));
        });

        test('finds no product element for the buy widget of the detail page', () => {
            document.body.innerHTML = '<form class="buy-widget"><button class="buy"></button></form>';

            expect(ProductPageHelper.getProductCard('product-1', document.querySelector('.buy'))).toBeNull();
        });

        test('reads the data of a custom product element', () => {
            document.body.innerHTML = `
                <article class="custom-teaser" data-product-information='{"id":"product-1","sku":"SW1","name":"Shirt","brand":"Brand","price":19.99}'>
                    <button class="custom-buy">Buy</button>
                </article>
            `;

            expect(ProductPageHelper.getProductCardData('product-1', document.querySelector('.custom-buy'))).toEqual({
                id: 'SW1',
                name: 'Shirt',
                brand: 'Brand',
                value: 19.99,
            });
        });

        test('uses the product element on a detail page instead of the page product', () => {
            window.activeRoute = 'frontend.detail.page';
            document.body.innerHTML = `
                <meta property="product:price:amount" content="99.99">
                <h1 class="product-detail-name">Page Product</h1>
                <div class="card product-box" data-product-information='{"id":"product-2","sku":"SW2","name":"Cross Selling Product","price":9.99}'>
                    <form class="buy-widget"><button class="buy"></button></form>
                </div>
            `;

            expect(ProductPageHelper.getProductData('product-2', document.querySelector('.buy'))).toMatchObject({
                id: 'SW2',
                name: 'Cross Selling Product',
                value: 9.99,
            });
        });

        test('uses the page product for the buy widget of the detail page', () => {
            window.activeRoute = 'frontend.detail.page';
            document.body.innerHTML = `
                <meta property="product:price:amount" content="99.99">
                <h1 class="product-detail-name">Page Product</h1>
                <form class="buy-widget"><button class="buy"></button></form>
            `;

            expect(ProductPageHelper.getProductData('product-1', document.querySelector('.buy'))).toMatchObject({
                name: 'Page Product',
                value: '99.99',
            });
        });

        test('reports the breadcrumb categories for a product element on a listing', () => {
            document.body.innerHTML = `
                ${breadcrumb}
                <div class="card product-box" data-product-information='{"id":"product-1"}'>
                    <button class="buy"></button>
                </div>
            `;

            expect(ProductPageHelper.getCategoriesFor('product-1', document.querySelector('.buy'))).toEqual({
                item_category: 'Clothing',
                item_category2: 'Shirts',
            });
        });

        test('reports the breadcrumb categories without the element of the interaction', () => {
            document.body.innerHTML = breadcrumb;

            expect(ProductPageHelper.getCategoriesFor('product-1')).toEqual({
                item_category: 'Clothing',
                item_category2: 'Shirts',
            });
        });

        test('reports no categories for a product element on a detail page', () => {
            window.activeRoute = 'frontend.detail.page';
            document.body.innerHTML = `
                ${breadcrumb}
                <div class="card product-box" data-product-information='{"id":"product-2"}'>
                    <button class="buy"></button>
                </div>
            `;

            expect(ProductPageHelper.getCategoriesFor('product-2', document.querySelector('.buy'))).toEqual({});
        });

        test('reports the breadcrumb categories for the page product of a detail page', () => {
            window.activeRoute = 'frontend.detail.page';
            document.body.innerHTML = `
                ${breadcrumb}
                <form class="buy-widget"><button class="buy"></button></form>
            `;

            expect(ProductPageHelper.getCategoriesFor('product-1', document.querySelector('.buy'))).toEqual({
                item_category: 'Clothing',
                item_category2: 'Shirts',
            });
        });
    });
});
