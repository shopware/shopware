let product = {};

// TODO See NEXT-6902: Use an own storefront project or make E2E tests independent from bundle
describe('Search - Storefront: Visual tests', () => {
    beforeEach(() => {
        cy.visit('');

        return cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category');
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
        });
    });

    it('@visual: check appearance of basic storefront search workflow', { tags: ['ct-storefront'] }, () => {
        cy.visit('/');
        cy.get('input[name=search]').type(product.name).type('{enter}');

        cy.contains('.search-headline', `One product found for "${product.name}"`);
        cy.contains('.cms-element-product-listing', product.name);

        cy.get('input[name=search]').clear().type('Non existent stuff');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('input[name=search]').type('{enter}');

        cy.contains('.search-headline', '0 products found for "Non existent stuff"');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Search] No result', '.cms-element-product-listing', { widths: [375, 1920] });
    });
});
