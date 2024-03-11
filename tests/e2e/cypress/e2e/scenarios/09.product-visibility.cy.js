/// <reference types="Cypress" />
/**
 * @package inventory
 */
import ProductPageObject from '../../support/pages/module/sw-product.page-object';

describe('Admin & Storefront - product visibility', () => {

    beforeEach(() => {
        cy.createProductFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@package: should handle a visible product', { tags: ['pa-inventory'] }, ()=>{

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'post',
        }).as('saveProduct');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'post',
        }).as('assignProductToCategory');

        const page = new ProductPageObject();

        // Add product to sales channel
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.contains('h2','Product name').should('be.visible');
        cy.get('.sw-product-detail__select-visibility').scrollIntoView()
            .typeMultiSelectAndCheck(Cypress.env('storefrontName'));
        cy.get('.advanced-visibility').click();
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-field__radio-option-checked [type]').check();
        cy.get('.sw-modal__footer .sw-button__content').click();
        cy.get('.sw-modal__body').should('not.be.visible');
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-button-process__content', 'Opslaan').should('be.visible');

        // Define the product under the home category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'category/index');
        cy.get('.tree-link > .sw-tree-item__label').click();
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.url().should('include', 'products');
        cy.get('.sw-select__selection > input').click()
            .type('Product name {enter}');
        cy.get('.sw-button-process').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.wait('@assignProductToCategory').its('response.statusCode').should('equal', 200);

        // Search product
        cy.visit('/');
        cy.get('.product-info [title="Product name"]').should('be.visible');
        cy.get('.header-search-input').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').should('be.visible');
    });

    it('@package: should hide the product in listings', ()=>{

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'post',
        }).as('saveProduct');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'post',
        }).as('assignProductToCategory');

        const page = new ProductPageObject();

        // Add product to sales channel
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.contains('h2','Product name').should('be.visible');
        cy.get('.sw-product-detail__select-visibility').scrollIntoView()
            .typeMultiSelectAndCheck(Cypress.env('storefrontName'));
        cy.get('.advanced-visibility').click();
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-grid-column--center:nth-of-type(4) [type]').check();
        cy.get('.sw-modal__footer .sw-button__content').click();
        cy.get('.sw-modal__body').should('not.be.visible');
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-button-process__content', 'Opslaan').should('be.visible');

        // Define the product under the home category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'category/index');
        cy.get('.tree-link > .sw-tree-item__label').click();
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.url().should('include', 'products');
        cy.get('.sw-select__selection > input').click()
            .type('Product name {enter}');
        cy.get('.sw-button-process').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.wait('@assignProductToCategory').its('response.statusCode').should('equal', 200);

        // Search product
        cy.visit('/');
        cy.contains('.alert-content', 'No products found').should('be.visible');
        cy.get('.header-search-input').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').should('be.visible');
    });

    it('@package: should hide the product in listing and search', ()=>{

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'post',
        }).as('saveProduct');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'post',
        }).as('assignProductToCategory');

        const page = new ProductPageObject();

        // Add product to sales channel
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.contains('h2','Product name').should('be.visible');
        cy.get('.sw-product-detail__select-visibility').scrollIntoView()
            .typeMultiSelectAndCheck(Cypress.env('storefrontName'));
        cy.get('.advanced-visibility').click();
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-grid-column--center:nth-of-type(5) [type]').check();
        cy.get('.sw-modal__footer .sw-button__content').click();
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-button-process__content', 'Opslaan').should('be.visible');

        // Define the product under the home category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'category/index');
        cy.get('.tree-link > .sw-tree-item__label').click();
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.url().should('include', 'products');
        cy.get('.sw-select__selection > input').click()
            .type('Product name {enter}');
        cy.get('.sw-button-process').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.wait('@assignProductToCategory').its('response.statusCode').should('equal', 200);

        // Search product
        cy.visit('/');
        cy.contains('.alert-content', 'No products found').should('be.visible');
        cy.get('.header-search-input').type('Product name');
        cy.contains('.search-suggest-no-result', 'No results found').should('be.visible');
    });
});


