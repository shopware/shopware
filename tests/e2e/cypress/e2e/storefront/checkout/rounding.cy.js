import CheckoutPageObject from '../../../support/pages/checkout.page-object';

let product = {};

/**
 * @package checkout
 */
describe('Checkout: Use rounding', () => {

    beforeEach(() => {
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

    it('@base @checkout: Run checkout with 0.50', { tags: ['pa-checkout'] }, () => {
        cy.intercept({
            url: '/api/currency/**',
            method: 'PATCH'
        }).as('saveData');

        cy.loginViaApi();

        cy.visit('/admin#/sw/settings/currency/detail/b7d2554b0ce847cd82f3ac9bd1c0dfca');

        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-settings-price-rounding__headline', 'Grand total').scrollIntoView();

        cy.get('.sw-settings-price-rounding__grand-interval-select')
            .typeSingleSelectAndCheck('0.50', '.sw-settings-price-rounding__grand-interval-select');

        cy.get('.sw-settings-currency-detail__save-action').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.icon--regular-checkmark-xs').should('be.visible');
        cy.get('.sw-loader').should('not.exist');

        cy.visit('/');

        cy.window().then((win) => {
            const page = new CheckoutPageObject();

            /** @deprecated tag:v6.5.0 - Use `CheckoutPageObject.elements.lineItem` instead */
            const lineItemSelector = win.features['v6.5.0.0'] ? '.line-item' : '.cart-item';

            cy.get('.header-search-input')
                .should('be.visible')
                .type(product.name);
            cy.contains('.search-suggest-product-name', product.name).click();
            cy.get('.product-detail-buy .btn-buy').click();

            // Off canvas
            cy.get(page.elements.offCanvasCart).should('be.visible');
            cy.get(`${lineItemSelector}-label`).contains(product.name);

            // Checkout
            cy.get('.offcanvas-cart-actions .btn-primary').click();

            cy.get('.checkout-aside-summary-value.checkout-aside-summary-total-rounded').contains('10.50');
            cy.get('.checkout-aside-summary-value.checkout-aside-summary-total').contains('10.51');
        });
    });
});
