// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Edit in various ways', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @catalogue: set list price', { tags: ['pa-inventory'] }, () => {
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

        cy.window().then((win) => {
            /** @deprecated tag:v6.5.0 - Use `CheckoutPageObject.elements.lineItem` instead */
            const lineItemSelector = win.features['v6.5.0.0'] ? '.line-item' : '.cart-item';

            cy.get('.product-box .product-badges > .badge').should('be.visible');
            cy.contains('.product-price', '49.98');
            cy.contains('.product-price .list-price', '100');
            cy.contains('.product-name', 'Product name').click();

            cy.get('.list-price-badge').should('be.visible');
            cy.contains('.product-detail-price.with-list-price', '49.98');
            cy.contains('.list-price-price', '100');

            cy.get('.btn-buy').click();
            cy.get('.offcanvas').should('be.visible');
            cy.contains(`.offcanvas ${lineItemSelector}-label`, 'Product name');
            cy.contains(`${lineItemSelector}-price`, '49.98');
        });
    });
});
