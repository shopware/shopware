const GeneralPageObject = require('./general.page-object.js');

class CheckoutPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);
        this.browser = browser;

        this.elements = {
            ...this.elements,
            ...{
                // General cart selectors
                cartItem: '.cart-item',

                // Cart widget
                cardWidget: '.cart-widget',

                // Offcanvas cart
                offCanvasCart: '.js-off-canvas',
                cartActions: '.cart-actions'
            }
        };
    }
}

module.exports = (browser) => {
    return new CheckoutPageObject(browser);
};
