import Feature from 'src/helper/feature.helper';
import ProductCategoryHelper from 'src/plugin/google-analytics/product-category.helper';

/**
 * Helper for extracting product data from DOM on product pages (detail, listing, wishlist)
 * For cart/checkout data, use LineItemHelper instead.
 *
 * A product element is any element with a `data-product-information` attribute holding a JSON
 * object with at least the product `id`, and optionally `sku`, `name`, `brand` and `price`. Core's
 * product box renders it, and an extension that renders products with its own markup adds the
 * attribute to be reported the same way. The attribute is the contract, not the `.product-box`
 * class.
 */
export default class ProductPageHelper {
    static PRODUCT_ELEMENT_SELECTOR = '[data-product-information]';

    static LISTING_SELECTOR = '.cms-element-product-listing-wrapper';

    /**
     * Gets product data from available sources (detail page or product card)
     * @param {string} productId
     * @param {HTMLElement|null} fallbackElement - Optional element to search for product card (e.g., form)
     * @returns {{id: string|undefined, name: string|undefined, brand: string|undefined, variant: string|undefined, currency: string|undefined, value: string|undefined}}
     */
    static getProductData(productId, fallbackElement = null) {
        const detailData = ProductPageHelper.getProductDetailData();

        // A product element on the detail page, such as cross selling or a product slider, shows
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
                // JSON-LD has no field for the selected options, so the variant is read from the DOM on both paths
                variant: ProductPageHelper.getVariant(),
                currency: productData.currency || window.currencyIsoCode,
                value: productData.value,
            };
        }

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
     * The product element a product is shown in, found through the element the interaction started
     * from, such as the buy form of a product box, or through its wishlist button. The buy widget of
     * the detail page is not a product element.
     *
     * @param {string} productId
     * @param {HTMLElement|null} fallbackElement
     * @returns {HTMLElement|null}
     */
    static getProductCard(productId, fallbackElement = null) {
        return fallbackElement?.closest?.(ProductPageHelper.PRODUCT_ELEMENT_SELECTOR)
            ?? document.querySelector(`.product-wishlist-${productId}`)?.closest(ProductPageHelper.PRODUCT_ELEMENT_SELECTOR)
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
     * Gets SKU from product detail page
     * @returns {string|undefined}
     */
    static getSku() {
        if (Feature.isActive('JSON_LD_DATA')) {
            return ProductPageHelper.getJsonLdProductData().sku;
        }

        return document.querySelector('[itemprop="sku"]')?.textContent.trim();
    }

    /**
     * Gets brand from product detail page
     * @returns {string|undefined}
     */
    static getBrand() {
        if (Feature.isActive('JSON_LD_DATA')) {
            return ProductPageHelper.getJsonLdProductData().brand;
        }

        return document.querySelector('[itemprop="brand"] [itemprop="name"]')?.content;
    }

    /**
     * Gets the selected variant options from the product detail page, e.g. `Red, L`
     * @returns {string|undefined}
     */
    static getVariant() {
        return document.querySelector('[data-product-variant]')?.getAttribute('data-product-variant') || undefined;
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
        const pricesElement = element?.closest?.('[data-product-prices]');
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
     * The breadcrumb categories for a product, if the breadcrumb is about it, see `isDescribedByBreadcrumb()`.
     *
     * @param {string} productId
     * @param {HTMLElement|null} element the element the interaction started from, if any
     * @returns {Object}
     */
    static getCategoriesFor(productId, element = null) {
        return ProductPageHelper.isDescribedByBreadcrumb(productId, element) ? ProductPageHelper.getCategories() : {};
    }

    /**
     * Whether the page breadcrumb is the category path of a product. It is for the product of a
     * detail page and for the products of the listing on a category page. A product element
     * anywhere else, such as a slider on a category page or cross selling on a detail page, can
     * show a product of any category.
     *
     * @param {string} productId
     * @param {HTMLElement|null} element the element the interaction started from, if any
     * @returns {boolean}
     */
    static isDescribedByBreadcrumb(productId, element = null) {
        const productElement = ProductPageHelper.getProductCard(productId, element);

        if (window.activeRoute === 'frontend.detail.page') {
            return !productElement;
        }

        return ProductPageHelper.isInPageListing(productElement);
    }

    /**
     * Whether a product element belongs to the product listing of the page. On a category page
     * the listing shows the products of that category.
     *
     * @param {HTMLElement|null} productElement
     * @returns {boolean}
     */
    static isInPageListing(productElement) {
        return window.activeRoute !== 'frontend.detail.page'
            && !!productElement?.closest(ProductPageHelper.LISTING_SELECTOR);
    }

    /**
     * The GA4 category properties of a product, requested from the storefront when the page does
     * not describe them.
     *
     * The page breadcrumb is used where it is about the product: the product of a detail page, or a
     * product of a category listing. Anywhere else, such as the homepage, a landing page, the
     * wishlist, a slider or a product element on a detail page, the path is requested from the storefront,
     * which resolves it through the Store API breadcrumb route. Product elements do not carry it,
     * because loading the categories of every product would slow down each render for an event that
     * only fires on a click. A failed request reports no categories.
     *
     * @param {string} productId
     * @param {HTMLElement|null} element the element the interaction started from, if any
     * @returns {Promise<Object>}
     */
    static async resolveCategories(productId, element = null) {
        const categories = ProductPageHelper.getCategoriesFor(productId, element);
        if (Object.keys(categories).length > 0) {
            return categories;
        }

        const url = window.router?.['frontend.analytics.product-categories'];
        if (!url || !productId) {
            return {};
        }

        try {
            const response = await fetch(`${url}?productId=${encodeURIComponent(productId)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                return {};
            }

            const resolved = ProductPageHelper.mapCategories(await response.json());

            // the line item of the product carries no categories, so the cart and checkout read them from here
            ProductCategoryHelper.remember(productId, resolved);

            return resolved;
        } catch {
            return {};
        }
    }

    /**
     * Gets category hierarchy from breadcrumbs (GA4 supports up to 5 levels)
     * @returns {Object}
     */
    static getCategories() {
        return ProductPageHelper.mapCategories(ProductPageHelper.getCategoryNames());
    }

    /**
     * Maps a category path, ordered from the top level down, to the GA4 category properties
     * @param {string[]|undefined} names
     * @returns {Object}
     */
    static mapCategories(names) {
        const categories = {};

        if (!Array.isArray(names)) {
            return categories;
        }

        names.slice(0, 5).forEach((name, index) => {
            const key = index === 0 ? 'item_category' : `item_category${index + 1}`;
            categories[key] = name;
        });

        return categories;
    }

    /**
     * Gets the names of the breadcrumb categories, at most 5 levels as GA4 supports
     * @returns {string[]}
     */
    static getCategoryNames() {
        const breadcrumbNodes = document.querySelectorAll('[aria-label="breadcrumb"] .breadcrumb-title');

        return [...breadcrumbNodes].slice(0, 5).map(node => node.textContent.trim());
    }
}
