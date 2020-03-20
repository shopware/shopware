import elements from '../sw-general.page-object';

export default class SettingsPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                countrySaveAction: '.sw-settings-country-detail__save-action',
                countryColumnName: '.sw-data-grid__cell--name',

                currencySaveAction: '.sw-settings-currency-detail__save-action',
                currencyColumnName: '.sw-data-grid__cell--name',

                languageSaveAction: '.sw-settings-language-detail__save-action',
                languageColumnName: '.sw-language-list__column-name',

                taxSaveAction: '.sw-settings-tax-detail__save-action',
                taxColumnName: '.sw-data-grid__cell--name',

                customerGroupSaveAction: '.sw-settings-customer-group-detail__save'
            }
        };
    }
}
