/// <reference types="Cypress" />

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

    it.skip('@catalogue: edit a product\'s search keyword', () => {
        cy.server();
        cy.route({
            url: `/api/v*/product/*`,
            method: 'patch'
        }).as('saveData');
        cy.route({
            url: '/api/v*/search/product',
            method: 'post'
        }).as('searchData');

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

        cy.get('input.sw-search-bar__input').type('YTN');
        cy.wait('@searchData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        })

        cy.get('.sw-product-list-grid').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');
    });
});
