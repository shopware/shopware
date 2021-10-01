let product = {};

describe('Test review function on Product page', () => {
    beforeEach(() => {
        return cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category')
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
        }).then(() => {
            cy.loginByGuestAccountViaApi();
        }).then(() => {
            cy.visit('/');
        });
    });

    it('Should show login form when guest review product', () => {
        cy.get('.header-search-input').should('be.visible');
        cy.get('.header-search-input').type(product.name);
        cy.get('.search-suggest-product-name').contains(product.name);
        cy.get('.search-suggest-product-price').contains(product.price[0].gross);
        cy.get('.search-suggest-product-name').click();
        cy.get('.product-detail-tabs #review-tab').click();
        cy.get('button.product-detail-review-teaser-btn').contains('Write a review!').click();

        cy.get('.product-detail-review-login .login-form').should('be.visible');
    });
});
