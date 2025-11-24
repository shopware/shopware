export default class LineItemHelper
{
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

            const categories = {};
            for (let i = 0; i < 5; i++) {
                const attrIndex = i === 0 ? '' : i + 1;
                const categoryValue = itemEl.getAttribute(`data-category-${attrIndex}`);

                if (categoryValue) {
                    const key = `item_category${attrIndex}`;
                    categories[key] = categoryValue;
                } else {
                    break;
                }
            }

            lineItems.push({
                ...itemData,
                ...categories,
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
}
