/* global cy */
import elements from '../sw-general.page-object';

export default class ShippingMethodPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                shippingSaveAction: '.sw-settings-shipping-method-detail__save-action',
                shippingBackToListViewAction: '.sw-icon.icon--default-action-settings.sw-icon--small'
            }
        };
    }

    createShippingMethod(name) {
        cy.get('input[name=sw-field--shippingMethod-name]').typeAndCheck(name);
        cy.get('.sw-settings-shipping-detail__delivery-time').typeSingleSelectAndCheck(
            '1-3 days',
            '.sw-settings-shipping-detail__delivery-time'
        );
        cy.get('.sw-settings-shipping-detail__top-rule').typeSingleSelectAndCheck(
            'Cart >= 0',
            '.sw-settings-shipping-detail__top-rule'
        );
        this.createShippingMethodTax();
        this.createShippingMethodPriceRule();
    }

    createShippingMethodPriceRule() {
        cy.get('.sw-settings-shipping-price-matrices').then(($el) => {
            if ($el.find('.sw-settings-shipping-price-matrix').length <= 0) {
                cy.get('.sw-settings-shipping-price-matrices__actions').scrollIntoView();
                cy.get('.sw-settings-shipping-price-matrices__actions .sw-button').click();

                cy.get('.sw-settings-shipping-price-matrices').scrollIntoView();
                cy.get('.sw-settings-shipping-price-matrix__empty--select-property').typeSingleSelect(
                    'Product quantity',
                    '.sw-settings-shipping-price-matrix__empty--select-property'
                );

                cy.get('.sw-settings-shipping-price-matrix__empty--select-property').should('not.exist');
            }
        });


        cy.get(`${this.elements.dataGridRow}--0 .sw-data-grid__cell--quantityStart input`).type('0');
        cy.get(`${this.elements.dataGridRow}--0 .sw-data-grid__cell--quantityEnd input`).type('12');

        cy.get(`${this.elements.dataGridRow}--0 .sw-settings-shipping-price-matrix__price input`).first().type('7.42');

        cy.get(`${this.elements.dataGridRow}--1 .sw-settings-shipping-price-matrix__price input`).first().clear();
        cy.get(`${this.elements.dataGridRow}--1 .sw-settings-shipping-price-matrix__price input`).first().type('8.00');
        cy.get(`${this.elements.dataGridRow}--1 .sw-settings-shipping-price-matrix__price input`).eq(1).click();
        cy.get(`${this.elements.dataGridRow}--1 .sw-settings-shipping-price-matrix__price input`).eq(2).should('have.value', '213.88');

        cy.get(this.elements.shippingSaveAction).click();
        cy.get(this.elements.successIcon).should('be.visible');
    }

    createShippingMethodTax() {
        cy.window().then((win) => {
            // Fixed
            cy.get('.sw-settings-shipping__tax-type-selection').typeSingleSelectAndCheck(
                'Fixed',
                '.sw-settings-shipping__tax-type-selection'
            );
            cy.get('.sw-settings-shipping__tax-rate').should('exist');
            cy.get('.sw-settings-shipping__tax-rate').typeSingleSelectAndCheck(
                'Standard rate',
                '.sw-settings-shipping__tax-rate'
            );

            // Auto
            cy.get('.sw-settings-shipping__tax-type-selection').typeSingleSelectAndCheck(
                'Auto',
                '.sw-settings-shipping__tax-type-selection'
            );
            cy.get('.sw-settings-shipping__tax-rate').should('not.exist');

            // Highest
            cy.get('.sw-settings-shipping__tax-type-selection').typeSingleSelectAndCheck(
                'Highest',
                '.sw-settings-shipping__tax-type-selection'
            );
            cy.get('.sw-settings-shipping__tax-rate').should('not.exist');
        });
    }
}
