import elements from '../sw-general.page-object';

export default class SettingsPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                countryListContent: '.sw-settings-country-list-grid',
                countrySaveAction: '.sw-settings-country-detail__save-action',
                countryColumnName: '.sw-data-grid__cell--name',

                countryStateListContent: '.sw-country-state-list__content',
                countryStateSaveAction: '.sw-country-state-detail__save-button',
                countryStateAddAction: '.sw-settings-country-detail__add-country-state-button',
                countryStateColumnName: '.sw-settings-country-detail__link',

                currencySaveAction: '.sw-settings-currency-detail__save-action',
                currencyColumnName: '.sw-data-grid__cell--name',

                deliveryTimeSaveAction: '.sw-settings-delivery-time-detail__save',
                deliveryTimeColumnName: '.sw-data-grid__cell--name',
                deliveryTimeColumnUnit: '.sw-data-grid__cell--unit',

                languageSaveAction: '.sw-settings-language-detail__save-action',
                languageColumnName: '.sw-language-list__column-name',

                taxSaveAction: '.sw-settings-tax-detail__save-action',
                taxColumnName: '.sw-data-grid__cell--name',

                customerGroupSaveAction: '.sw-settings-customer-group-detail__save',
                customerGroupColumnName: '.sw-data-grid__cell--name',
                customerGroupColumnTaxDisplay: '.sw-data-grid__cell--displayGross',

                salutationListContent: '.sw-settings-salutation-list-grid',

                numberRangeSaveAction: '.sw-settings-number-range-detail__save-action',
                numberRangeColumnName: '.sw-data-grid__cell--name',

                integrationListConent: '.sw-integration-list'
            }
        };
    }
}
