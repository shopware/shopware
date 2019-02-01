const GeneralPageObject = require('../sw-general.page-object');

class OrderPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = Object.assign(this.elements, {
            userMetadata: '.sw-user-card__metadata'
        });
    }
}

module.exports = (browser) => {
    return new OrderPageObject(browser);
};
