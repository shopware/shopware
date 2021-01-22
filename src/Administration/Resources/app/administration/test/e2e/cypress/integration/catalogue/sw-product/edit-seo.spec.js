// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Edit in various ways', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createPropertyFixture({
                    options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }]
                });
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@catalogue: set list price', () => {
        cy.server();
        cy.route({
            url: '/api/v*/search/product',
            method: 'post'
        }).as('saveProduct');

        const page = new ProductPageObject();

        // go to product detail page
        cy.get('.sw-data-grid__cell-content :nth-child(2) a').click();

        // go to variant tab
        cy.get('.sw-product-detail__tab-variants').click();

        // open variant generation modal
        cy.get('.sw-product-detail-variants__generated-variants__empty-state .sw-button').click();

        // generate variants
        page.generateVariants('Color', [0, 1], 2);

        cy.wait('@saveProduct').then(xhr => {
            expect(xhr).to.have.property('status', 200);
        });

        // go back to detail tab
        cy.get('.sw-product-detail__tab-general').click();

        // scroll to switch and click it
        cy.get('.sw-product-seo-form .sw-field--switch input')
            .scrollIntoView()
            .check();

        cy.get('.sw-product-seo-form .sw-select')
            .typeSingleSelectAndCheck('Green', '.sw-product-seo-form .sw-select');

        cy.wait('@searchCall').then(xhr => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-button-process').click();

        // checking if product got saved. 'product call' alias comes from the product.generateVariants method
        cy.wait('@productCall').then(xhr => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.visit(`${Cypress.config('baseUrl')}/Product-name/RS-333.2`);

        cy.get('link[rel="canonical"]')
            .should('have.attr', 'href', `${Cypress.config('baseUrl')}/Product-name/RS-333.1`);

        cy.visit(`${Cypress.config('baseUrl')}/Product-name/RS-333.1`);

        cy.get('link[rel="canonical"]')
            .should('have.attr', 'href', `${Cypress.config('baseUrl')}/Product-name/RS-333.1`);
    });
});
