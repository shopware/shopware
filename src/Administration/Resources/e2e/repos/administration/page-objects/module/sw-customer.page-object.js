const GeneralPageObject = require('../sw-general.page-object');

class CustomerPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);


        this.elements = {
            ...this.elements,
            ...{
                customerForm: '.sw-customer-base-form',
                customerMailInput: 'input[name=sw-field--customer-email]',
                customerMetaData: '.sw-customer-card__metadata',
                customerSaveAction: '.smart-bar__actions button.sw-button--primary',
                columnName: `${this.elements.dataGridColumn}--firstName`
            }
        };
    }

    createBasicAddress() {
        this.browser
            .fillSelectField('select[name=sw-field--address-salutationId]', 'Mr.')
            .fillField('input[name=sw-field--address-firstName]', 'Harry')
            .fillField('input[name=sw-field--address-lastName]', 'Potter')
            .fillField('input[name=sw-field--address-street]', 'Ligusterweg 4')
            .fillField('input[name=sw-field--address-zipcode]', '333333')
            .fillField('input[name=sw-field--address-city]', 'Little Whinging')
            .fillSelectField('select[name=sw-field--address-countryId]', 'Great Britain')
            .tickCheckbox('input[name=sw-field--this-isDefaultBillingAddressId]', true)
            .click(`${this.elements.modal}__footer button${this.elements.primaryButton}`);
    }
}

module.exports = (browser) => {
    return new CustomerPageObject(browser);
};
