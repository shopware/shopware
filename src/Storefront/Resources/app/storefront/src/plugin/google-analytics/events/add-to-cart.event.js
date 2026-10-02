import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';
import ProductPageHelper from 'src/plugin/google-analytics/product-page.helper';

export default class AddToCartEvent extends EventAwareAnalyticsEvent
{
    /* eslint-disable no-unused-vars */
    /**
     * @param {string} controllerName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} actionName @deprecated tag:v6.8.0 - Will be removed, use activeRoute instead.
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(controllerName, actionName, activeRoute) {
        return true;
    }
    /* eslint-enable no-unused-vars */

    getPluginName() {
        return 'AddToCart';
    }

    getEvents() {
        return {
            'beforeFormSubmit':  this._beforeFormSubmit.bind(this),
        };
    }

    _beforeFormSubmit(event) {
        if (!this.active) {
            return;
        }

        const formData = event.detail;
        const formElement = event.target;
        let productId = null;

        formData.forEach((value, key) => {
            if (key.endsWith('[id]')) {
                productId = value;
            }
        });

        if (!productId) {
            console.warn('[Google Analytics Plugin] Product ID could not be fetched. Skipping.');
            return;
        }

        // Get product data - uses detail page meta tags or falls back to product card data
        const productData = ProductPageHelper.getProductData(productId, formElement);

        this._addPayload(formData, productId, formElement);

        const quantity = formData.get(`lineItems[${productId}][quantity]`);
        // the meta price is the cheapest tier, so a graduated price is resolved from the quantity
        const price = ProductPageHelper.getGraduatedPrice(formElement, quantity)
            ?? productData.value
            ?? ProductPageHelper.getValue();
        const value = price === undefined ? undefined : Number(price) * (Number(quantity) || 1);

        this.pushEvent('add_to_cart', {
            'currency': productData.currency || ProductPageHelper.getCurrency(),
            'value': value,
            'items': [{
                'item_id': productData.id ?? productId,
                'item_name': formData.get('product-name') || productData.name,
                'quantity': quantity,
                'price': price,
                'item_brand': formData.get('brand-name') || productData.brand,
                'item_variant': productData.variant,
                ...ProductPageHelper.getCategories(),
            }],
        });
    }

    /**
     * Hands brand and category to the cart with the add, so the checkout events can read them
     * from the line item payload without the cart or the checkout loading anything. This only
     * runs for a sales channel with analytics and after consent, so a shop without tracking
     * sends nothing extra.
     *
     * The buy widget of the product detail page already posts both, from the category of the
     * product. A product box sends its own brand and the category the shopper is browsing. A page
     * without breadcrumb, such as the homepage or the search, sends no category, and neither does
     * a product box on a product detail page, whose breadcrumb belongs to the other product.
     *
     * @param {FormData} formData
     * @param {string} productId
     * @param {HTMLElement|null} formElement
     * @private
     */
    _addPayload(formData, productId, formElement) {
        const productBox = formElement?.closest?.('.product-box');
        if (!productBox) {
            return;
        }

        const prefix = `lineItems[${productId}][payload]`;
        const brand = ProductPageHelper.getProductCardData(productId, formElement).brand;

        if (brand && !formData.has(`${prefix}[manufacturerName]`)) {
            formData.set(`${prefix}[manufacturerName]`, brand);
        }

        const hasCategories = [...formData.keys()].some(key => key.startsWith(`${prefix}[categoryNames]`));
        if (hasCategories || window.activeRoute === 'frontend.detail.page') {
            return;
        }

        ProductPageHelper.getCategoryNames().forEach((name, index) => {
            formData.set(`${prefix}[categoryNames][${index}]`, name);
        });
    }
}
