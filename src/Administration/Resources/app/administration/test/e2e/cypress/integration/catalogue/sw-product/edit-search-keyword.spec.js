// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

const page = new ProductPageObject();

describe('Product: Search Keyword product', () => {
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

    it('@catalogue: edit a products search keyword', () => {
        cy.server();
        cy.route({
            url: '/api/product/*',
            method: 'patch'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'post'
        }).as('searchData');

        // Data grid should be visible
        cy.get('.sw-product-list-grid').should('be.visible');

        // Ensure product from `createProductFixture` is at correct position
        cy.get(`${page.elements.dataGridRow}--0`).contains('Product name');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Create new search keyword
        cy.get('.sw-product-category-form__search-keyword-field input').clear();
        cy.get('.sw-product-category-form__search-keyword-field input').type('YTN');
        cy.get('.sw-product-category-form__search-keyword-field input')
            .type('{enter}');

        // Save product with search keyword
        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(page.elements.successIcon).should('be.visible');

        cy.get(page.elements.smartBarBack).click();

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('YTN');

        cy.wait('@searchData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-product-list-grid').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');

        // Ensure the search found the correct product
        cy.get(`${page.elements.dataGridRow}--0`).contains('Product name');
    });
});
