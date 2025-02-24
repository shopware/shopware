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
            lineItems.push({
                id: itemEl.getAttribute('data-id'),
                name: itemEl.getAttribute('data-name'),
                quantity: itemEl.getAttribute('data-quantity'),
                price: itemEl.getAttribute('data-price'),
                currency: lineItemsContainer.getAttribute('data-currency'),
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
