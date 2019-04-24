const GeneralPageObject = require('../sw-general.page-object');

class CustomerPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                countrySaveAction: '.sw-settings-country-detail__save-action',
                countryColumnName: '.sw-country-list__column-name',

                currencySaveAction: '.sw-settings-currency-detail__save-action',
                currencyColumnName: '.sw-currency-list__column-name',

                languageSaveAction: '.sw-settings-language-detail__save-action',
                languageColumnName: '.sw-language-list__column-name',

                taxSaveAction: '.sw-settings-tax-detail__save-action',
                taxColumnName: '.sw-tax-list__column-name',

                customerGroupSaveAction: '.sw-settings-customer-group-detail__save'
            }
        };
    }
}

module.exports = (browser) => {
    return new CustomerPageObject(browser);
};
