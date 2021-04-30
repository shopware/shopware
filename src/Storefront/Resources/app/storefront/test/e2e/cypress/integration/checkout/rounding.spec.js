import CheckoutPageObject from "../../support/pages/checkout.page-object";

let product = {};

describe('Checkout: Use rounding', () => {

    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.createProductFixture().then(() => {
                    return cy.createDefaultFixture('category')
                }).then(() => {
                    cy.createProductFixture({
                        name: 'Test product',
                        productNumber: 'TEST-1234',
                        price: [
                            {
                                currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                                linked: true,
                                gross: 10.51
                            }
                        ]
                    });
                }).then((result) => {
                    product = result;
                    return cy.createCustomerFixtureStorefront()
                });
            });
    });

    it('@base @checkout: Run checkout with 0.50', () => {
        cy.server();
        cy.route({
            url: '/api/currency/**',
            method: 'patch'
        }).as('saveData');

        cy.loginViaApi();

        cy.visit('/admin#/sw/settings/currency/detail/b7d2554b0ce847cd82f3ac9bd1c0dfca');

        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-settings-price-rounding__headline', 'Grand total').scrollIntoView();

        cy.get('.sw-settings-price-rounding__grand-interval-select')
            .typeSingleSelectAndCheck('0.50', '.sw-settings-price-rounding__grand-interval-select');

        cy.get('.sw-settings-currency-detail__save-action').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.icon--small-default-checkmark-line-medium').should('be.visible');
        });
        cy.get('.sw-loader').should('not.exist');

        cy.visit('/');

        const page = new CheckoutPageObject();

        cy.get('.header-search-input')
            .should('be.visible')
            .type(product.name);
        cy.contains('.search-suggest-product-name', product.name).click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get(`${page.elements.offCanvasCart}.is-open`).should('be.visible');
        cy.get(`${page.elements.cartItem}-label`).contains(product.name);

        // Checkout
        cy.get('.offcanvas-cart-actions .btn-primary').click();

        cy.get('.checkout-aside-summary-value.checkout-aside-summary-total-rounded').contains('10.50');
        cy.get('.checkout-aside-summary-value.checkout-aside-summary-total').contains('10.51');
    });
});
