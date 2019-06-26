const GeneralPageObject = require('./general.page-object');

export default class AccountPageObject extends GeneralPageObject {
    constructor() {
        super();

        this.elements = {
            ...this.elements,
            ...{
                accountRoot: '.account',
                accountHeadline: '.account-welcome',
                accountMenu: '.js-account-menu-dropdown',
                accountSidebar: '.account-sidebar',

                // Register - Login
                registerCard: '.register-card',
                registerForm: '.register-form',
                registerSubmit: '.register-submit',
                loginCard: '.login-card',
                loginForm: '.login-form',
                loginSubmit: '.login-submit',

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
