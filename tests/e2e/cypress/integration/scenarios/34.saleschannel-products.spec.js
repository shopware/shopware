/// <reference types="Cypress" />

describe('Add and remove products from saleschannel', () => {
    beforeEach(() => {
        cy.loginViaApi().then(() => {
            cy.createProductFixture();
        });
    });

    afterEach(() => {
        // Remove product from the sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.contains('E2E install test').click();
        cy.get('[title="Producten"]').click();
        cy.get('.sw-data-grid__actions-menu').click();
        cy.get('.sw-context-menu__content').contains('Remove').click();
        cy.contains('Nog geen producten toegevoegd').should('be.visible');

        // Verify product not exist in the storefront
        cy.visit('/');
        cy.get('.header-search-input').should('be.visible').type('Product name');
        cy.contains('.search-suggest-container', 'No results found').should('be.visible');
    });

    it('@package: should add via product selection', () => {
        // Add product from the sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.contains('E2E install test').click();
        cy.get('[title="Producten"]').click();
        cy.get('.sw-button.sw-button--ghost').click();
        cy.get('.sw-modal__header').contains('Add products').should('be.visible');
        cy.get('.sw-data-grid__select-all [type]').check();
        cy.get('.sw-data-grid__bulk').contains('1').should('be.visible');
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();
        cy.get('.sw-data-grid__cell--name').contains('Product name');

        // Verify product in the storefront
        cy.visit('/');
        cy.get('.header-search-input').should('be.visible').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').should('be.visible');
    });

    it('@package: should add via category selection', () => {
        // Add product to the home
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-tree-item__label').contains('Home').click();
        cy.get('[title="Producten"]').click();
        cy.get('input[placeholder="Producten zoeken en toewijzen …"]').click();
        cy.contains('.sw-select-result-list__content', 'Product name').click();

        // Add product to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.contains('E2E install test').click();
        cy.get('[title="Producten"]').click();
        cy.get('.sw-button.sw-button--ghost').click();
        cy.get('.sw-modal__header').contains('Add products').should('be.visible');
        cy.get('[title="Category selection"]').click();
        cy.get('.sw-tree-item__selection [type]').click();
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();

        // Verify product in the storefront
        cy.visit('/');
        cy.get('.header-search-input').should('be.visible').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').should('be.visible');
    });

    it('@package: should add and remove via product group selection', () => {
        // Add product to a dynamic product group
        cy.visit(`${Cypress.env('admin')}#/sw/product/stream/index`);
        cy.get('.sw-product-stream-list__create-action').click();
        cy.get('input[name=sw-field--productStream-name]').typeAndCheck('Dynamic Products');
        cy.get('[class="sw-product-stream-value sw-product-stream-value--grow-2"] .sw-entity-single-select__selection').click();
        cy.get('.sw-select-result-list__content').contains('Product name').click();
        cy.get('.sw-button.sw-button--primary.sw-product-stream-detail__save-action').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-button-process__content').contains('Opslaan').should('be.visible');

        // Add product to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.contains('E2E install test').click();
        cy.get('[title="Producten"]').click();
        cy.get('.sw-button.sw-button--ghost').click();
        cy.get('.sw-modal__header').contains('Add products').should('be.visible');
        cy.get('[title="Product group selection"]').click();
        cy.get('.sw-data-grid--actions .sw-data-grid__header [type]').check();
        cy.get('.sw-data-grid__bulk').contains('1').should('be.visible');
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();
        cy.get('.sw-data-grid__cell--name').contains('Product name');

        // Verify product in the storefront
        cy.visit('/');
        cy.get('.header-search-input').should('be.visible').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').should('be.visible');
    });
});
