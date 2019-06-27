const GeneralPageObject = require('./general.page-object');

export default class CheckoutPageObject extends GeneralPageObject {
    constructor() {
        super();

        this.elements = {
            ...this.elements,
            ...{
                // General cart selectors
                cartItem: '.cart-item',

                // Cart widget
                cardWidget: '.cart-widget',

                // Offcanvas cart
                offCanvasCart: '.offcanvas',
                cartActions: '.cart-actions'
            }
        };
    }
}
