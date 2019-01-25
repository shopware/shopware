const GeneralPageObject = require('../sw-general.page-object');

class CustomerPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = Object.assign(this.elements, {
            customerForm: '.sw-customer-base-form',
            customerMailInput: 'input[name=sw-field--customer-email]',
            customerMetaData: '.sw-user-card__metadata',
            customerSaveAction: '.smart-bar__actions button.sw-button--primary',
            columnName: '.sw-customer-list__column-customer-name'
        });
    }
}

module.exports = (browser) => {
    return new CustomerPageObject(browser);
};
