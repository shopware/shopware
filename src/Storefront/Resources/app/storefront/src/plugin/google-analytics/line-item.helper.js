export default class LineItemHelper
{
    /**
     * Extract GA4 category data from an element's data-category-* attributes.
     * GA4 supports up to 5 category levels (item_category, item_category2, ..., item_category5).
     * @param {HTMLElement} element
     * @returns {Object}
     */
    static getCategoriesFromElement(element) {
        const categories = {};

        for (let i = 1; i <= 5; i++) {
            const categoryValue = element.getAttribute(`data-category-${i}`);

            if (categoryValue) {
                const key = i === 1 ? 'item_category' : `item_category${i}`;
                categories[key] = categoryValue;
            } else {
                break;
            }
        }

        return categories;
    }

    /**
     * @param {string|null} productId
     * @returns { Object[] }
     */
    static getLineItems(productId = null) {
        const lineItemsContainer = document.querySelector('.hidden-line-items-information');
        const lineItemDataElements = lineItemsContainer?.querySelectorAll('.hidden-line-item') ?? [];
        const lineItems = [];

        lineItemDataElements.forEach(itemEl => {
            if (productId && itemEl.getAttribute('data-id') !== productId) {
                return;
            }

            const itemData = {
                item_id: itemEl.getAttribute('data-sku') ?? itemEl.getAttribute('data-id'),
                item_name: itemEl.getAttribute('data-name'),
                quantity: itemEl.getAttribute('data-quantity'),
                price: itemEl.getAttribute('data-price'),
                item_brand: itemEl.getAttribute('data-brand'),
            };

            lineItems.push({
                ...itemData,
                ...LineItemHelper.getCategoriesFromElement(itemEl),
            });
        });

        return lineItems;
    }

    /**
     * `value` is the position price of the cart or order: the sum of all line items including
     * promotion discounts, without shipping. Item prices stay the unit prices before discounts.
     *
     * A page that renders no cart has no container, so the properties are empty rather than a
     * `TypeError` that takes the surrounding event down with it.
     *
     * @returns { Object }
     */
    static getAdditionalProperties() {
        const lineItemsContainer = document.querySelector('.hidden-line-items-information');

        return {
            currency: lineItemsContainer?.getAttribute('data-currency'),
            shipping: lineItemsContainer?.getAttribute('data-shipping'),
            value: lineItemsContainer?.getAttribute('data-value'),
            tax: lineItemsContainer?.getAttribute('data-tax'),
            coupon: LineItemHelper.getCoupon(),
        };
    }

    /**
     * GA4 reports a single coupon per event, so all applied promotion codes are joined.
     * @returns { string }
     */
    static getCoupon() {
        const couponElements = document.querySelectorAll('.hidden-line-items-information .hidden-line-item-coupon');
        const codes = new Set([...couponElements].map(element => element.getAttribute('data-code')));

        return [...codes].join(', ');
    }

    /**
     * Get product data for a specific product ID from hidden line items
     * @param {string} productId
     * @returns {Object|null}
     */
    static getProductData(productId) {
        if (!document.querySelector('.hidden-line-items-information')) {
            return null;
        }

        const lineItems = LineItemHelper.getLineItems(productId);
        const lineItem = lineItems.find(() => true);
        if (!lineItem) {
            return null;
        }

        const additionalProperties = LineItemHelper.getAdditionalProperties();

        // Extract categories from line item
        const categories = {};
        for (const [key, value] of Object.entries(lineItem)) {
            if (key.startsWith('item_category')) {
                categories[key] = value;
            }
        }

        return {
            id: lineItem.item_id,
            name: lineItem.item_name,
            brand: lineItem.item_brand,
            value: lineItem.price,
            currency: additionalProperties.currency,
            categories,
        };
    }
}
