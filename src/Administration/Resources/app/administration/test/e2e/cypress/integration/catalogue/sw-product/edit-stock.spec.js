/// <reference types="Cypress" />

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

    it('@base @catalogue: check out-of-stock-behavior without clearance', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-card__title', 'Deliverability').scrollIntoView();
        cy.get('#sw-field--product-stock').type('0');
        cy.get('input[name="sw-field--product-is-closeout"]').click();

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Put product in cart
        cy.visit('/');
        cy.get('.btn-buy').should('be.visible');
        cy.get('.btn-buy').click();
        cy.get('.offcanvas').should('be.visible');
        cy.get('.offcanvas .cart-item-label').contains('1x Product name');
    });

    it('@base @catalogue: check product with full stock', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-card__title', 'Deliverability').scrollIntoView();
        cy.get('#sw-field--product-stock').type('0');

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Put product in cart
        cy.visit('/');
        cy.get('.btn-buy').should('be.visible');
        cy.get('.btn-buy').click();
        cy.get('.offcanvas').should('be.visible');
        cy.get('.offcanvas .cart-item-label').contains('1x Product name');
    });

    it('@base @catalogue: check out-of-stock-behavior with clearance', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-card__title', 'Deliverability').scrollIntoView();
        cy.get('#sw-field--product-stock').clear();
        cy.get('#sw-field--product-stock').type('0');
        cy.get('input[name="sw-field--product-is-closeout"]').click();

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Make sure we fail to put product in cart
        cy.visit('/');
        cy.get('.btn-buy').should('not.exist');
        cy.get('a[title="Details"]').should('be.visible');
        cy.get('a[title="Details"]').click();
        cy.get('.delivery-status-indicator.bg-danger').should('be.visible');
        cy.get('.delivery-information').contains('No longer available');
        cy.get('.btn-buy').should('not.exist');
    });
});
