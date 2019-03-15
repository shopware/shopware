const GeneralPageObject = require('../sw-general.page-object');

class OrderPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                userMetadata: '.sw-order-user-card__metadata'
            }
        };
    }
}

module.exports = (browser) => {
    return new OrderPageObject(browser);
};
