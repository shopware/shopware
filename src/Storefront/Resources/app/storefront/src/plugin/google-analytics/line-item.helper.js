import DomAccessHelper from 'src/helper/dom-access.helper';

export default class LineItemHelper
{
    /**
     * @returns { Object[] }
     */
    static getLineItems() {
        const lineItemsContainer = DomAccessHelper.querySelector(document, '.hidden-line-items-information');
        const lineItemDataElements = DomAccessHelper.querySelectorAll(lineItemsContainer, '.hidden-line-item');
        const lineItems = [];

        lineItemDataElements.forEach(itemEl => {
            lineItems.push({
                id: DomAccessHelper.getDataAttribute(itemEl, 'id'),
                name: DomAccessHelper.getDataAttribute(itemEl, 'name'),
                quantity: DomAccessHelper.getDataAttribute(itemEl, 'quantity'),
                price: DomAccessHelper.getDataAttribute(itemEl, 'price'),
                currency: DomAccessHelper.getDataAttribute(lineItemsContainer, 'currency'),
            });
        });

        return lineItems;
    }

    /**
     * @returns { Object }
     */
    static getAdditionalProperties() {
        const lineItemsContainer = DomAccessHelper.querySelector(document, '.hidden-line-items-information');

        return {
            currency: DomAccessHelper.getDataAttribute(lineItemsContainer, 'currency'),
            shipping: DomAccessHelper.getDataAttribute(lineItemsContainer, 'shipping'),
            value: DomAccessHelper.getDataAttribute(lineItemsContainer, 'value'),
            tax: DomAccessHelper.getDataAttribute(lineItemsContainer, 'tax'),
        };
    }
}
