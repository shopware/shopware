// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Edit in various ways', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@base @catalogue: check out-of-stock-behavior without clearance', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-card__title', 'Deliverability').scrollIntoView();
        cy.get('#sw-field--product-stock').type('0');
        cy.get('input[name="sw-field--product-is-closeout"]').click();

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        // Put product in cart
        cy.visit('/');
        cy.get('.btn-buy').should('be.visible');
        cy.get('.btn-buy').click();
        cy.get('.offcanvas').should('be.visible');

        cy.window().then(() => {
            cy.contains(`.offcanvas .line-item-label`, 'Product name');
        });
    });

    it('@base @catalogue: check product with full stock', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-card__title', 'Deliverability').scrollIntoView();
        cy.get('#sw-field--product-stock').type('0');

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        // Put product in cart
        cy.visit('/');

        cy.window().then(() => {
            cy.get('.btn-buy').should('be.visible');
            cy.get('.btn-buy').click();
            cy.get('.offcanvas').should('be.visible');
            cy.contains(`.offcanvas .line-item-label`, 'Product name');
        });
    });

    it('@base @catalogue: check out-of-stock-behavior with clearance', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-card__title', 'Deliverability').scrollIntoView();
        cy.get('#sw-field--product-stock').clear();
        cy.get('#sw-field--product-stock').type('0');
        cy.get('input[name="sw-field--product-is-closeout"]').click();

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        // Make sure we fail to put product in cart
        cy.visit('/');
        cy.get('.btn-buy').should('not.exist');
        cy.get('a[title="Details"]').should('be.visible');
        cy.get('a[title="Details"]').click();
        cy.get('.delivery-status-indicator.bg-danger').should('be.visible');
        cy.contains('.delivery-information', 'No longer available');
        cy.get('.btn-buy').should('not.exist');
    });
});
