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
                price: DomAccessHelper.getDataAttribute(itemEl, 'price')
            });
        });

        return lineItems;
    }
}
