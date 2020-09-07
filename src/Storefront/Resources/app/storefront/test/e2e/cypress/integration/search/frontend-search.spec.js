let product = {};

describe('Searches for products', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            }).then(() => {
            cy.visit('');
        });

        return cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category')
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
        });
    });

    it('@search does some simple testing of the search', () => {
        cy.visit('/');
        cy.get('input[name=search]').type(product.name).type('{enter}');

        cy.get('.search-headline').contains('One product found for "' + product.name + '"');
        cy.get('.cms-element-product-listing').contains(product.name);

        cy.get('input[name=search]').clear().type('Non existent stuff');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('input[name=search]').type('{enter}');

        cy.get('.search-headline').contains('0 products found for "Non existent stuff"');

        cy.get('.cms-element-product-listing').contains('No products found');


        cy.visit('/');
        cy.get('input[name=search]').type(product.name.slice(4)).get('.search-suggest-container').contains(product.name).type('{downarrow}');

        cy.get('.product-detail-name').contains(product.name);
    });
});
