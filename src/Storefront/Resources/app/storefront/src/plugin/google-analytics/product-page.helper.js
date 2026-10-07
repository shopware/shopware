import Feature from 'src/helper/feature.helper';

/**
 * Helper for extracting product data from DOM on product pages (detail, listing, wishlist)
 * For cart/checkout data, use LineItemHelper instead.
 */
export default class ProductPageHelper {
    /**
     * Gets product data from available sources (detail page or product card)
     * @param {string} productId
     * @param {HTMLElement|null} fallbackElement - Optional element to search for product card (e.g., form)
     * @returns {{id: string|undefined, name: string|undefined, brand: string|undefined, variant: string|undefined, currency: string|undefined, value: string|undefined}}
     */
    static getProductData(productId, fallbackElement = null) {
        const detailData = ProductPageHelper.getProductDetailData();

        // A product box on the detail page, such as cross selling or a product slider, shows
        // another product than the page, so only the product the page is about uses its data.
        if (detailData.name && !ProductPageHelper.getProductCard(productId, fallbackElement)) {
            return detailData;
        }

        const cardData = ProductPageHelper.getProductCardData(productId, fallbackElement);
        return {
            id: cardData.id,
            name: cardData.name,
            brand: cardData.brand,
            variant: cardData.variant,
            currency: detailData.currency,
            value: cardData.value,
        };
    }

    /**
     * Gets product data from product detail page
     * @returns {{id: string|undefined, name: string|undefined, brand: string|undefined, variant: string|undefined, currency: string|undefined, value: string|undefined}}
     */
    static getProductDetailData() {
        if (Feature.isActive('JSON_LD_DATA')) {
            const productData = ProductPageHelper.getJsonLdProductData();

            return {
                id: productData.sku,
                name: productData.name,
                brand: productData.brand,
                // JSON-LD has no field for the selected option string, so the variant is read from
                // the DOM on both paths.
                variant: ProductPageHelper.getVariant(),
                currency: productData.currency || window.currencyIsoCode,
                value: productData.value,
            };
        }

        return {
            id: ProductPageHelper.getSku(),
            name: ProductPageHelper.getName(),
            brand: ProductPageHelper.getBrand(),
            variant: ProductPageHelper.getVariant(),
            currency: ProductPageHelper.getCurrency(),
            value: ProductPageHelper.getValue(),
        };
    }

    /**
     * The product box a product is shown in, found through its wishlist button or the element the
     * interaction started from, such as the buy form of the box. The buy widget of the detail page
     * is not a product box.
     *
     * @param {string} productId
     * @param {HTMLElement|null} fallbackElement
     * @returns {HTMLElement|null}
     */
    static getProductCard(productId, fallbackElement = null) {
        return fallbackElement?.closest('.product-box')
            ?? document.querySelector(`.product-wishlist-${productId}`)?.closest('.product-box')
            ?? null;
    }

    /**
     * Gets product data from product card (listing page)
     * @param {string} productId
     * @param {HTMLElement|null} fallbackElement - Optional element to search for product card
     * @returns {{id: string|undefined, name: string|undefined, brand: string|undefined, variant: string|undefined, value: string|undefined}}
     */
    static getProductCardData(productId, fallbackElement = null) {
        const productCard = ProductPageHelper.getProductCard(productId, fallbackElement);

        if (!productCard?.dataset.productInformation) {
            return {};
        }

        try {
            const info = JSON.parse(productCard.dataset.productInformation);
            return {
                id: info.sku ?? productId,
                name: info.name,
                brand: info.brand,
                variant: info.variant,
                value: info.price,
            };
        } catch {
            return {};
        }
    }

    /**
     * The GA4 category properties of a product the page does not carry a path for.
     *
     * On the product detail page the breadcrumb is the path of the product itself. Anywhere else,
     * such as a listing, a slider or a Shopping Experience page, and for a product box on the
     * detail page, such as cross selling, the breadcrumb describes the page,
     * so the path is requested from the storefront, which resolves it through the Store API
     * breadcrumb route. Product boxes do not carry it, because loading every category of every
     * product would slow down each render for an event that only fires on a click. The
     * breadcrumb is only used as a fallback when that request fails.
     *
     * @param {string} productId
     * @param {HTMLElement|null} element the element the interaction started from, if any
     * @returns {Promise<Object>}
     */
    static async resolveCategories(productId, element = null) {
        const url = window.router?.['frontend.analytics.product-categories'];
        const isPageProduct = window.activeRoute === 'frontend.detail.page'
            && !ProductPageHelper.getProductCard(productId, element);

        if (isPageProduct || !url || !productId) {
            return ProductPageHelper.getCategories();
        }

        try {
            const response = await fetch(`${url}?productId=${encodeURIComponent(productId)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                return ProductPageHelper.getCategories();
            }

            return ProductPageHelper.mapCategories(await response.json());
        } catch {
            return ProductPageHelper.getCategories();
        }
    }

    /**
     * Maps a category path, ordered from the top level down, to the GA4 category properties.
     * @param {string[]|undefined} names
     * @returns {Object}
     */
    static mapCategories(names) {
        const categories = {};

        (names ?? []).slice(0, 5).forEach((name, index) => {
            if (name) {
                categories[index === 0 ? 'item_category' : `item_category${index + 1}`] = name;
            }
        });

        return categories;
    }

    /**
     * Gets the product name from the product detail page
     * @returns {string|undefined}
     */
    static getName() {
        // @deprecated tag:v6.8.0 - The `[itemprop="name"]` fallback will be removed with the
        // microdata. It covers a statically configured CMS product-name element, which renders the
        // microdata without the `.product-detail-name` class.
        return document.querySelector('.product-detail-name')?.textContent.trim()
            || document.querySelector('[itemtype="https://schema.org/Product"] [itemprop="name"]')?.textContent.trim();
    }

    /**
     * Gets SKU from product detail page
     * @returns {string|undefined}
     */
    static getSku() {
        if (Feature.isActive('JSON_LD_DATA')) {
            return ProductPageHelper.getJsonLdProductData().sku;
        }

        // @deprecated tag:v6.8.0 - The `[itemprop="sku"]` fallback will be removed, the microdata is replaced by JSON-LD.
        return document.querySelector('.product-detail-ordernumber')?.textContent.trim()
            || document.querySelector('[itemprop="sku"]')?.textContent.trim();
    }

    /**
     * Gets brand from product detail page
     * @returns {string|undefined}
     */
    static getBrand() {
        if (Feature.isActive('JSON_LD_DATA')) {
            return ProductPageHelper.getJsonLdProductData().brand;
        }

        // @deprecated tag:v6.8.0 - The `[itemprop="brand"]` fallback will be removed, the microdata is replaced by JSON-LD.
        return document.querySelector('meta[property="product:brand"]')?.content
            || document.querySelector('[itemprop="brand"] [itemprop="name"]')?.content;
    }

    /**
     * The unit price a graduated price table charges for a quantity. The `product:price:amount`
     * meta tag carries the cheapest tier for search engines, so it is the wrong price for most
     * quantities of a product with graduated prices.
     *
     * Every tier but the last applies up to and including its quantity, the last from its quantity.
     *
     * @param {HTMLElement|null} element an element inside the buy widget
     * @param {number|string} quantity
     * @returns {number|undefined} undefined without graduated prices
     */
    static getGraduatedPrice(element, quantity) {
        const pricesElement = element?.closest('[data-product-prices]');
        if (!pricesElement) {
            return undefined;
        }

        let tiers;
        try {
            tiers = JSON.parse(pricesElement.getAttribute('data-product-prices'));
        } catch {
            return undefined;
        }

        if (!Array.isArray(tiers) || tiers.length === 0) {
            return undefined;
        }

        const amount = Number(quantity) || 1;
        const tier = tiers.find((candidate, index) => index < tiers.length - 1 && amount <= candidate.quantity)
            ?? tiers[tiers.length - 1];

        return tier.price;
    }

    /**
     * Gets the selected variant options from the product detail page, e.g. `Red, L`
     * @returns {string|undefined}
     */
    static getVariant() {
        return document.querySelector('[data-product-variant]')?.getAttribute('data-product-variant');
    }

    /**
     * Gets currency from meta tag or global variable
     * @returns {string|undefined}
     */
    static getCurrency() {
        if (Feature.isActive('JSON_LD_DATA')) {
            return ProductPageHelper.getJsonLdProductData().currency || window.currencyIsoCode;
        }

        return document.querySelector('meta[property="product:price:currency"]')?.content || window.currencyIsoCode;
    }

    /**
     * Gets product value/price from meta tag
     * @returns {string|undefined}
     */
    static getValue() {
        if (Feature.isActive('JSON_LD_DATA')) {
            return ProductPageHelper.getJsonLdProductData().value;
        }

        return document.querySelector('meta[property="product:price:amount"]')?.content;
    }

    /**
     * Gets product data from the JSON-LD product script
     * @returns {{name: string|undefined, sku: string|undefined, brand: string|undefined, currency: string|undefined, value: string|number|undefined}}
     */
    static getJsonLdProductData() {
        const productScripts = document.querySelectorAll('script[type="application/ld+json"]');

        for (const productScript of productScripts) {
            try {
                const structuredData = JSON.parse(productScript.textContent);
                const productData = [structuredData, ...(structuredData['@graph'] ?? [])].find((data) => {
                    const types = Array.isArray(data['@type']) ? data['@type'] : [data['@type']];

                    return types.includes('Product') || types.includes('ProductGroup');
                });

                if (!productData) {
                    continue;
                }

                const variant = productData.hasVariant?.[0];
                const product = variant ? { ...productData, ...variant } : productData;
                const offers = Array.isArray(product.offers) ? product.offers[0] : product.offers;

                return {
                    name: product.name,
                    sku: product.sku,
                    brand: product.brand?.name,
                    currency: offers?.priceCurrency,
                    value: offers?.price ?? offers?.lowPrice,
                };
            } catch {
                continue;
            }
        }

        return {};
    }

    /**
     * The breadcrumb categories for a product, if the breadcrumb is about it. On the product
     * detail page the breadcrumb is the path of the page product, so a product box there, such as
     * cross selling, reports none rather than the path of another product.
     *
     * @param {string} productId
     * @param {HTMLElement|null} element the element the interaction started from, if any
     * @returns {Object}
     */
    static getCategoriesFor(productId, element = null) {
        if (window.activeRoute === 'frontend.detail.page' && ProductPageHelper.getProductCard(productId, element)) {
            return {};
        }

        return ProductPageHelper.getCategories();
    }

    /**
     * Gets category hierarchy from breadcrumbs (GA4 supports up to 5 levels)
     * @returns {Object}
     */
    static getCategories() {
        const categories = {};

        ProductPageHelper.getCategoryNames().forEach((name, index) => {
            const key = index === 0 ? 'item_category' : `item_category${index + 1}`;
            categories[key] = name;
        });

        return categories;
    }

    /**
     * Gets the category names of the page breadcrumb, from the top level down, at most the five
     * levels GA4 supports
     * @returns {string[]}
     */
    static getCategoryNames() {
        return [...document.querySelectorAll('[aria-label="breadcrumb"] .breadcrumb-title')]
            .slice(0, 5)
            .map(node => node.textContent.trim());
    }
}
