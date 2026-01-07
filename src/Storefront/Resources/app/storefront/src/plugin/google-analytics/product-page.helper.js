/**
 * Helper for extracting product data from DOM on product pages (detail, listing, wishlist)
 * For cart/checkout data, use LineItemHelper instead.
 */
export default class ProductPageHelper {
    /**
     * Gets product data from available sources (detail page or product card)
     * @param {string} productId
     * @param {HTMLElement|null} fallbackElement - Optional element to search for product card (e.g., form)
     * @returns {{name: string|undefined, brand: string|undefined, currency: string|undefined, value: string|undefined}}
     */
    static getProductData(productId, fallbackElement = null) {
        const detailData = ProductPageHelper.getProductDetailData();

        if (detailData.name) {
            return detailData;
        }

        const cardData = ProductPageHelper.getProductCardData(productId, fallbackElement);
        return {
            name: cardData.name,
            brand: cardData.brand,
            currency: detailData.currency,
            value: cardData.value,
        };
    }

    /**
     * Gets product data from product detail page
     * @returns {{name: string|undefined, brand: string|undefined, currency: string|undefined, value: string|undefined}}
     */
    static getProductDetailData() {
        return {
            name: document.querySelector('.product-detail-name')?.textContent.trim(),
            brand: ProductPageHelper.getBrand(),
            currency: ProductPageHelper.getCurrency(),
            value: ProductPageHelper.getValue(),
        };
    }

    /**
     * Gets product data from product card (listing page)
     * @param {string} productId
     * @param {HTMLElement|null} fallbackElement - Optional element to search for product card
     * @returns {{name: string|undefined, brand: string|undefined, value: string|undefined}}
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
                name: info.name,
                brand: info.brand,
                value: info.price,
            };
        } catch {
            return {};
        }
    }

    /**
     * Gets brand from product detail page
     * @returns {string|undefined}
     */
    static getBrand() {
        return document.querySelector('[itemprop="brand"] [itemprop="name"]')?.content;
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

