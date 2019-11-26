const GeneralPageObject = require('../sw-general.page-object');

export default class ShippingMethodPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                shippingSaveAction: '.sw-settings-shipping-method-detail__save-action',
                shippingBackToListViewAction: '.sw-icon.icon--default-action-settings.sw-icon--small'
            }
        };
    }

    createShippingMethod(name) {
        cy.get('input[name=sw-field--shippingMethod-name]').typeAndCheck(name);
        cy.get('.sw-settings-shipping-detail__delivery-time').typeLegacySelectAndCheck(
            '1-3 days', {
                searchTerm: '1-3 days'
            }
        );
        cy.get('.sw-settings-shipping-detail__top-rule').typeSingleSelectAndCheck(
            'Cart >= 0',
            '.sw-settings-shipping-detail__top-rule'
        );
        this.createShippingMethodPriceRule();
    }

    createShippingMethodPriceRule() {
        cy.get('.sw-settings-shipping-price-matrices').scrollIntoView();

        this.selectPriceCalculation('.sw-settings-shipping-price-matrix__empty .sw-select', {
            optionSelector: '.sw-select-option--0',
            value: 'Line item count'
        });

        cy.get('.sw-settings-shipping-price-matrix__empty .sw-select').should('not.exist');
        cy.get(`${this.elements.dataGridRow}--0 .sw-data-grid__cell--quantityStart`).dblclick();
        cy.get('.is--inline-edit').should('be.visible');
        cy.get(`${this.elements.dataGridRow}--0 .sw-data-grid__cell--quantityStart input`).type('0');
        cy.get(`${this.elements.dataGridRow}--0 .sw-data-grid__cell--quantityEnd input`).type('12');
        cy.get(`${this.elements.dataGridRow}--0 .sw-data-grid__cell--price input`).type('7.42');

        cy.get('.sw-data-grid__inline-edit-save').click();
        cy.get(`${this.elements.dataGridRow}--1 .sw-data-grid__cell--quantityStart`).dblclick();
        cy.get('.is--inline-edit').should('be.visible');
        cy.get(`${this.elements.dataGridRow}--1 .sw-data-grid__cell--price input`).clear();
        cy.get(`${this.elements.dataGridRow}--1 .sw-data-grid__cell--price input`).type('5.00');
        cy.get('.sw-data-grid__inline-edit-save').click();
        cy.get('.sw-data-grid__inline-edit-save').should('not.exist');
        cy.get(this.elements.successIcon).should('not.exist');
        cy.get(this.elements.shippingSaveAction).click();
        cy.get(this.elements.successIcon).should('be.visible');
    }

    selectPriceCalculation(selector, { optionSelector, value }) {
        cy.get(selector).should('be.visible');
        cy.get(selector).click();
        cy.get(optionSelector).contains(value);
        cy.get(optionSelector).click();
    }
}
