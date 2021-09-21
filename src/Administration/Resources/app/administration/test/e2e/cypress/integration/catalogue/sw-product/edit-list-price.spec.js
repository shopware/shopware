// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Edit in various ways', () => {
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

    it('@base @catalogue: set list price', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/calculate-price`,
            method: 'POST'
        }).as('calculateData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-card__title', 'Prices').scrollIntoView();
        cy.get('.sw-list-price-field__list-price #sw-price-field-gross').clear();
        cy.get('.sw-list-price-field__list-price #sw-price-field-gross').typeAndCheck('100');
        cy.contains('.sw-card__title', 'Prices').click();

        cy.wait('@calculateData').its('response.statusCode').should('equal', 200);

        cy.get(page.elements.productSaveAction).should('be.enabled');
        cy.get(page.elements.productSaveAction).click();
        cy.get('.sw-loader').should('not.exist');

        // Verify updated product
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        // Verify product's list price in Storefront
        cy.visit('/');

        cy.get('.product-box .product-badges > .badge').should('be.visible');
        cy.get('.product-price').contains('64');
        cy.get('.product-price .list-price').contains('100');
        cy.contains('.product-name', 'Product name').click();

        cy.get('.list-price-badge').should('be.visible');
        cy.get('.product-detail-price.with-list-price').contains('64');
        cy.get('.list-price-price').contains('100');

        cy.get('.btn-buy').click();
        cy.get('.offcanvas').should('be.visible');
        cy.get('.offcanvas .cart-item-label').contains('1x Product name');
        cy.get('.cart-item-price').contains('64');
    });
});
