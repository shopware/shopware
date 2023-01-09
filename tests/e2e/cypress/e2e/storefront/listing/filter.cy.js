/// <reference types="cypress" />

import elements from '../../../support/pages/sw-general.page-object';

describe('Filter on startpage', () => {
    beforeEach(() => {
        return cy.createProductFixture({
            name: 'First product',
            productNumber: 'RS-123',
        }).then(() => {
            // Creates a product with manufacturer
            return cy.createProductFixture({
                name: 'Second product',
                manufacturerId: null,
                productNumber: 'RS-345',
            });
        }).then(() => {
            // Creates a product with manufacturer
            return cy.createProductFixture({
                name: 'Third product',
                manufacturerId: null,
                productNumber: 'RS-234',
            });
        }).then(() => {
            cy.visit('/');
        });
    });

    it('Filter for manufacturer', { tags: ['pa-inventory'] }, () => {
        const actualItems = 3;
        const filteredItems = 1;
        const manufacturer = 'shopware AG';

        cy.get(elements.productCard).as('productCard');

        cy.get('@productCard').should('have.length', actualItems);

        cy.get(elements.manufacturerFilter).click();

        cy.contains(elements.filterLabel, manufacturer).click({
            force: true,
        });
        cy.url().should('contain', '?manufacturer=');
        cy.get('.has-element-loader').should('not.exist');

        cy.get('@productCard').should('have.length', filteredItems);

        cy.get('@productCard').first().get('.product-name').click();

        cy.get(elements.productDetailManufacturerLink).should(
            'contain',
            manufacturer,
        );
    });
});
