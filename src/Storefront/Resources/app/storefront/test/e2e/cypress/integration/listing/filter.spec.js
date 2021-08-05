/// <reference types="cypress" />

import generalPageObject from '../../support/pages/general.page-object';

describe('Filter on startpage', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                // Takes care on API authorization
                cy.loginViaApi();
            })
            .then(() => {
                // Creates a product with manufacturer
                return cy.createProductFixture({
                    name: 'First product',
                    productNumber: 'RS-123'
                });
            })
            .then(() => {
                // Creates a product with manufacturer
                return cy.createProductFixture({
                    name: 'Second product',
                    manufacturerId: null,
                    productNumber: 'RS-345'
                });
            })
            .then(() => {
                // Creates a product with manufacturer
                return cy.createProductFixture({
                    name: 'Third product',
                    manufacturerId: null,
                    productNumber: 'RS-234'
                });
            })
            .then(() => {
                cy.visit('/');
            });
    });

    it("Filter for manufacturer", () => {
        const page = new generalPageObject();

        const actualItems = 3;
        const filteredItems = 1;
        const manufacturer = 'shopware AG';

        cy.get(page.elements.productCard).as('productCard');

        cy.get('@productCard').should('have.length', actualItems);

        cy.get(page.elements.manufacturerFilter).click();

        cy.contains(page.elements.filterLabel, manufacturer).click({
            force: true,
        });
        cy.url().should('contain', '?manufacturer=');
        cy.get('.has-element-loader').should('not.exist');

        cy.get("@productCard").should('have.length', filteredItems);

        cy.get("@productCard").first().click();

        cy.get(page.elements.productDetailManufacturerLink).should(
            'contain',
            manufacturer
        );
    });
});
