const GeneralPageObject = require('./general.page-object.js');

class AccountPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);
        this.browser = browser;

        this.elements = {
            ...this.elements,
            ...{
                accountRoot: '.account',
                accountHeadline: '.account-welcome',
                accountMenu: '.js-account-widget-dropdown',
                registerCard: '.register-card',
                accountSidebar: '.account-sidebar',

                // Address
                addressRoot: '.account-address',
                addressForm: '.account-address-form',
                addressBox: '.address-box',
                overViewBillingAddress: '.overview-billing-address',
                overViewShippingAddress: '.overview-shipping-address'
            }
        };
    }
}

module.exports = (browser) => {
    return new AccountPageObject(browser);
};
