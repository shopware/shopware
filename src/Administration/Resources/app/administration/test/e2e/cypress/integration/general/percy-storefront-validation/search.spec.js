let product = {};

// TODO See NEXT-6902: Use an own storefront project or make E2E tests independent from bundle
describe('Search - Storefront: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            }).then(() => {
                cy.visit('');
            });

        return cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category');
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
        });
    });

    it('@visual: check appearance of basic storefront search workflow', () => {
        cy.visit('/');
        cy.get('input[name=search]').type(product.name).type('{enter}');

        cy.get('.search-headline').contains(`One product found for "${product.name}"`);
        cy.get('.cms-element-product-listing').contains(product.name);

        cy.get('input[name=search]').clear().type('Non existent stuff');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('input[name=search]').type('{enter}');

        cy.get('.search-headline').contains('0 products found for "Non existent stuff"');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Search] No result', '.cms-element-product-listing', { widths: [375, 1920] });
    });
});
