// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Edit list prices of context prices', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@base @rule @product: creates context price rules', () => {
        cy.window().then(() => {
            const page = new ProductPageObject();
            const emptySelectRule = '.sw-product-detail-context-prices__empty-state-select-rule';

            // Request we want to wait for later
            cy.intercept({
                url: `${Cypress.env('apiPath')}/_action/sync`,
                method: 'POST'
            }).as('saveData');

            // Edit base data of product
            cy.clickContextMenuItem(
                '.sw-entity-listing__context-menu-edit-action',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`
            );
            cy.get('.sw-product-detail__tab-advanced-prices').click();

            // Select price rule group
            cy.get(`${emptySelectRule}`)
                .typeSingleSelect('All customers', `${emptySelectRule}`);

            cy.get('.sw-product-detail-context-prices__toolbar').should('be.visible');
            cy.get('[placeholder="∞"').should('be.visible');
            cy.get('[placeholder="∞"').type('3');
            cy.get('[placeholder="∞"').type('{enter}');

            cy.get('.sw-data-grid__row--1').should('be.visible');
            cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--price-EUR .sw-list-price-field__list-price #sw-price-field-gross')
                .type('100')
                .type('{enter}');
            cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--price-EUR .sw-list-price-field__list-price #sw-price-field-net')
                .should('have.value', '84.033613445378');
            cy.get('.sw-data-grid__row--1 .sw-data-grid__cell--price-EUR .sw-list-price-field__list-price #sw-price-field-gross')
                .type('100')
                .type('{enter}');
            cy.get('.sw-data-grid__row--1 .sw-data-grid__cell--price-EUR .sw-list-price-field__list-price #sw-price-field-net')
                .should('have.value', '84.033613445378');

            cy.get(page.elements.productSaveAction).click();

            // Verify updated product
            cy.wait('@saveData').its('response.statusCode').should('equal', 200);
            cy.get(page.elements.smartBarBack).click();
            cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
                .contains('Product name');

            // Verify in storefront
            cy.visit('/');
            cy.get('.product-box').contains('From €64.00*');
            cy.get('input[name=search]').type('Product name');
            cy.get('.search-suggest-container').should('be.visible');
            cy.get('.search-suggest-product-name')
                .contains('Product name')
                .click();

            cy.get('.product-detail-name').contains('Product name');
            cy.get('.product-detail-advanced-list-price-wrapper').contains('100.00');
            cy.get('.product-detail-price').contains('64.00');
        });
    });
});
