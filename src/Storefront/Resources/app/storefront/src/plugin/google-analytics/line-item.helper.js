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
     * @returns { Object[] }
     */
    static getLineItems() {
        const lineItemsContainer = document.querySelector('.hidden-line-items-information');
        const lineItemDataElements = lineItemsContainer.querySelectorAll('.hidden-line-item');
        const lineItems = [];

        lineItemDataElements.forEach(itemEl => {
            const itemData = {
                id: itemEl.getAttribute('data-id'),
                name: itemEl.getAttribute('data-name'),
                quantity: itemEl.getAttribute('data-quantity'),
                price: itemEl.getAttribute('data-price'),
                brand: itemEl.getAttribute('data-brand'),
            };

            lineItems.push({
                ...itemData,
                ...LineItemHelper.getCategoriesFromElement(itemEl),
            });
        });

        return lineItems;
    }

    /**
     * @returns { Object }
     */
    static getAdditionalProperties() {
        const lineItemsContainer = document.querySelector('.hidden-line-items-information');

        return {
            currency: lineItemsContainer.getAttribute('data-currency'),
            shipping: lineItemsContainer.getAttribute('data-shipping'),
            value: lineItemsContainer.getAttribute('data-value'),
            tax: lineItemsContainer.getAttribute('data-tax'),
        };
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

        const lineItems = LineItemHelper.getLineItems();
        const lineItem = lineItems.find(item => item.id === productId);
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
            name: lineItem.name,
            brand: lineItem.brand,
            value: lineItem.price,
            currency: additionalProperties.currency,
            categories,
        };
    }
}
