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

        if (detailData.name) {
            return detailData;
        }

        const cardData = ProductPageHelper.getProductCardData(productId, fallbackElement);
        return {
            id: cardData.id,
            name: cardData.name,
            brand: cardData.brand,
            variant: cardData.variant,
            categories: cardData.categories,
            currency: detailData.currency,
            value: cardData.value,
        };
    }

    /**
     * Gets product data from product detail page
     * @returns {{id: string|undefined, name: string|undefined, brand: string|undefined, variant: string|undefined, currency: string|undefined, value: string|undefined}}
     */
    static getProductDetailData() {
        return {
            id: ProductPageHelper.getSku(),
            name: document.querySelector('.product-detail-name')?.textContent.trim(),
            brand: ProductPageHelper.getBrand(),
            variant: ProductPageHelper.getVariant(),
            currency: ProductPageHelper.getCurrency(),
            value: ProductPageHelper.getValue(),
        };
    }

    /**
     * Gets product data from product card (listing page)
     * @param {string} productId
     * @param {HTMLElement|null} fallbackElement - Optional element to search for product card
     * @returns {{id: string|undefined, name: string|undefined, brand: string|undefined, variant: string|undefined, value: string|undefined}}
     */
    static getProductCardData(productId, fallbackElement = null) {
        let productCard = document.querySelector(`.product-wishlist-${productId}`)?.closest('.product-box');

        // Fallback: find product card from provided element (e.g., form on wishlist page)
        if (!productCard && fallbackElement) {
            productCard = fallbackElement.closest('.product-box');
        }

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
                categories: ProductPageHelper.mapCategories(info.categories),
            };
        } catch {
            return {};
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
     * Gets SKU from product detail page
     * @returns {string|undefined}
     */
    static getSku() {
        // @deprecated tag:v6.8.0 - The `[itemprop="sku"]` fallback will be removed, the microdata is replaced by JSON-LD.
        return document.querySelector('.product-detail-ordernumber')?.textContent.trim()
            || document.querySelector('[itemprop="sku"]')?.textContent.trim();
    }

    /**
     * Gets brand from product detail page
     * @returns {string|undefined}
     */
    static getBrand() {
        // @deprecated tag:v6.8.0 - The `[itemprop="brand"]` fallback will be removed, the microdata is replaced by JSON-LD.
        return document.querySelector('meta[property="product:brand"]')?.content
            || document.querySelector('[itemprop="brand"] [itemprop="name"]')?.content;
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
        return document.querySelector('meta[property="product:price:currency"]')?.content || window.currencyIsoCode;
    }

    /**
     * Gets product value/price from meta tag
     * @returns {string|undefined}
     */
    static getValue() {
        return document.querySelector('meta[property="product:price:amount"]')?.content;
    }

    /**
     * Gets category hierarchy from breadcrumbs (GA4 supports up to 5 levels)
     * @returns {Object}
     */
    static getCategories() {
        const breadcrumbNodes = document.querySelectorAll('[aria-label="breadcrumb"] .breadcrumb-title');
        const categories = {};

        breadcrumbNodes.forEach((node, index) => {
            if (index < 5) {
                const key = index === 0 ? 'item_category' : `item_category${index + 1}`;
                categories[key] = node.textContent.trim();
            }
        });

        return categories;
    }
}

