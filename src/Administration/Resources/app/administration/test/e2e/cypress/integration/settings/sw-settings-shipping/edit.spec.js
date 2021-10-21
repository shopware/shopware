// / <reference types="Cypress" />

import ShippingPageObject from '../../../support/pages/module/sw-shipping.page-object';

describe('Shipping: Edit in various ways', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createShippingFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
            });
    });

    it('@base @settings: edit shipping price matrix', () => {
        const page = new ShippingPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/shipping-method/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            cy.setEntitySearchable('shipping_method', 'name');
        });

        // Edit base data
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Luftpost');
        cy.clickContextMenuItem(
            '.sw-settings-shipping-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('input[name=sw-field--shippingMethod-name]').clearTypeAndCheck('Wasserpost');

        // Test shipping price matrix

        cy.get('.sw-settings-shipping-price-matrices__actions').scrollIntoView();
        cy.get('.sw-settings-shipping-price-matrices__actions .sw-button').click();

        cy.get('.sw-settings-shipping-price-matrices').scrollIntoView();
        cy.get('.sw-settings-shipping-price-matrix__empty--select-property').typeSingleSelect(
            'Product quantity',
            '.sw-settings-shipping-price-matrix__empty--select-property'
        );
        cy.get('.sw-settings-shipping-price-matrix__empty--select-property').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--quantityStart input`).clear().type('1');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--quantityEnd input`).clear().type('6');
        cy.get(`${page.elements.dataGridRow}--0 .sw-settings-shipping-price-matrix__price input`).eq(0).clear().type('7.42');
        cy.get(`${page.elements.dataGridRow}--0 .sw-settings-shipping-price-matrix__price input`).eq(1).clear().type('3');

        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--quantityStart input`).clear().type('4');
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--quantityEnd input`).clear().type('12');
        cy.get(`${page.elements.dataGridRow}--1 .sw-settings-shipping-price-matrix__price input`).eq(0).clear().type('6');
        cy.get(`${page.elements.dataGridRow}--1 .sw-settings-shipping-price-matrix__price input`).eq(1).clear().type('2.8');

        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--quantityStart input`).clear().type('13');
        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--quantityEnd input`).clear().type('25');
        cy.get(`${page.elements.dataGridRow}--2 .sw-settings-shipping-price-matrix__price input`).eq(0).clear().type('5');
        cy.get(`${page.elements.dataGridRow}--2 .sw-settings-shipping-price-matrix__price input`).eq(1).clear().type('2.4');

        cy.get(`${page.elements.dataGridRow}--3 .sw-data-grid__cell--quantityStart input`).clear().type('2');
        cy.get(`${page.elements.dataGridRow}--3 .sw-settings-shipping-price-matrix__price input`).eq(0).clear().type('4.6');
        cy.get(`${page.elements.dataGridRow}--3 .sw-settings-shipping-price-matrix__price input`).eq(1).clear().type('1.9');

        cy.get(`${page.elements.dataGridRow}--3 .sw-settings-shipping-price-matrix__price input`).eq(0).click();

        // check if values get updated correctly
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--quantityStart input`).should('have.value', '1');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--quantityEnd input`).should('have.value', '6');
        cy.get(`${page.elements.dataGridRow}--0 .sw-settings-shipping-price-matrix__price input`).eq(0).should('have.value', '7.42');
        cy.get(`${page.elements.dataGridRow}--0 .sw-settings-shipping-price-matrix__price input`).eq(1).should('have.value', '3');
        cy.get(`${page.elements.dataGridRow}--0 .sw-settings-shipping-price-matrix__price input`).eq(2).should('have.value', '198.37369999999999');
        cy.get(`${page.elements.dataGridRow}--0 .sw-settings-shipping-price-matrix__price input`).eq(3).should('have.value', '80.205');

        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--quantityStart input`).should('have.value', '6');
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--quantityEnd input`).should('have.value', '12');
        cy.get(`${page.elements.dataGridRow}--1 .sw-settings-shipping-price-matrix__price input`).eq(0).should('have.value', '6');
        cy.get(`${page.elements.dataGridRow}--1 .sw-settings-shipping-price-matrix__price input`).eq(1).should('have.value', '2.8');
        cy.get(`${page.elements.dataGridRow}--1 .sw-settings-shipping-price-matrix__price input`).eq(2).should('have.value', '160.41');
        cy.get(`${page.elements.dataGridRow}--1 .sw-settings-shipping-price-matrix__price input`).eq(3).should('have.value', '74.85799999999999');

        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--quantityStart input`).should('have.value', '13');
        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--quantityEnd input`).should('have.value', '25');
        cy.get(`${page.elements.dataGridRow}--2 .sw-settings-shipping-price-matrix__price input`).eq(0).should('have.value', '5');
        cy.get(`${page.elements.dataGridRow}--2 .sw-settings-shipping-price-matrix__price input`).eq(1).should('have.value', '2.4');
        cy.get(`${page.elements.dataGridRow}--2 .sw-settings-shipping-price-matrix__price input`).eq(2).should('have.value', '133.675');
        cy.get(`${page.elements.dataGridRow}--2 .sw-settings-shipping-price-matrix__price input`).eq(3).should('have.value', '64.164');

        cy.get(`${page.elements.dataGridRow}--3 .sw-data-grid__cell--quantityStart input`).should('have.value', '25');
        cy.get(`${page.elements.dataGridRow}--3 .sw-data-grid__cell--quantityEnd input`).should('have.value', '');
        cy.get(`${page.elements.dataGridRow}--3 .sw-settings-shipping-price-matrix__price input`).eq(0).should('have.value', '4.6');
        cy.get(`${page.elements.dataGridRow}--3 .sw-settings-shipping-price-matrix__price input`).eq(1).should('have.value', '1.9');
        cy.get(`${page.elements.dataGridRow}--3 .sw-settings-shipping-price-matrix__price input`).eq(2).should('have.value', '122.981');
        cy.get(`${page.elements.dataGridRow}--3 .sw-settings-shipping-price-matrix__price input`).eq(3).should('have.value', '50.796499999999995');

        cy.get('.sw-settings-shipping-price-matrices__actions button').should('be.enabled');

        cy.get('.sw-settings-shipping-price-matrix__top-container-rule-select').typeSingleSelectAndCheck(
            'All customers',
            '.sw-settings-shipping-price-matrix__top-container-rule-select'
        );

        cy.get('.sw-settings-shipping-price-matrices__actions button').should('not.be.disabled');
        cy.get('.sw-settings-shipping-price-matrices__actions button').click();

        cy.get('.sw-settings-shipping-price-matrix').eq(1).should('be.visible');

        cy.get('.sw-settings-shipping-price-matrices').scrollIntoView();
        cy.get('.sw-settings-shipping-price-matrix__empty--select-property').typeSingleSelect(
            'Weight',
            '.sw-settings-shipping-price-matrix__empty--select-property'
        );
        cy.get('.sw-settings-shipping-price-matrix__empty--select-property').should('not.exist');

        // End test of shipping price matrix

        cy.get(page.elements.shippingSaveAction).click();

        // Verify shipping method
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--name`).should('be.visible')
            .contains('Wasserpost');
    });
});
