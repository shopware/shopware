import CheckoutPageObject from '../../../support/pages/checkout.page-object';
import AccountPageObject from '../../../support/pages/account.page-object';

const devices = [{
    model: 'macbook-15',
    orientation: 'portrait',
}, {
    model: 'ipad-2',
    orientation: 'portrait',
}, {
    model: 'iphone-6+',
    orientation: 'portrait',
}, {
    model: 'iphone-6+',
    orientation: 'landscape',
}];
let product = {};

/**
 * @package checkout
 */
describe('Checkout: Login as customer and run checkout in various viewports', () => {

    beforeEach(() => {
        return cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category')
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
            return cy.createCustomerFixtureStorefront()
        }).then(() => {
            // Avoid cookie consent banner because it can block UI on small resolutions.
            // It can be operated by the cookie consent UI but we skip it here due to better test performance.
            // Cookie consent UI is covered by `cookie-bar.spec.js`.
            cy.setCookie('cookie-preference', '1');

            cy.visit('/');
        })
    });

    devices.forEach(device => {
        context(`Checkout on ${device.model} resolution (${device.orientation})`, () => {
            beforeEach(() => {
                cy.viewport(device.model, device.orientation)
            });

            it('@base @checkout: Run checkout', { tags: ['pa-checkout'] }, () => {
                const page = new CheckoutPageObject();
                const accountPage = new AccountPageObject();

                cy.window().then((win) => {
                    /** @deprecated tag:v6.5.0 - Use `CheckoutPageObject.elements.lineItem` instead */
                    const lineItemSelector = win.features['v6.5.0.0'] ? '.line-item' : '.cart-item';

                    if (device.model === 'iphone-6+' && device.orientation === 'portrait') {
                        cy.get('.search-toggle-btn').click();
                    }

                    // Product detail
                    cy.get('.header-search-input')
                        .type(product.name);
                    cy.get('.search-suggest-product-name').contains(product.name);
                    cy.get('.search-suggest-product-name').click();

                    cy.get('.product-detail-buy .btn-buy').click();

                    // Off canvas
                    cy.get(page.elements.offCanvasCart).should('be.visible');
                    cy.get(`${lineItemSelector}-label`).contains(product.name);

                    // Checkout
                    cy.get('.offcanvas-cart-actions .btn-primary').click();

                    // Login
                    cy.get('.checkout-main').should('be.visible');
                    cy.get('.login-collapse-toggle').click();

                    cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
                    cy.get('#loginPassword').typeAndCheckStorefront('shopware');
                    cy.get(`${accountPage.elements.loginSubmit} [type="submit"]`).click();

                    // Confirm
                    cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');

                    cy.get('.checkout-confirm-tos-label').scrollIntoView();
                    cy.get('.checkout-confirm-tos-label').click(1, 1);
                    cy.get('.confirm-address').contains('Pep Eroni');
                    cy.get(`${lineItemSelector}-details-container ${lineItemSelector}-label`).contains(product.name);
                    cy.get(`${lineItemSelector}-total-price`).contains(product.price[0].gross);
                    cy.get(`${lineItemSelector}-total-price`).contains(product.price[0].gross);

                    // Finish checkout
                    cy.get('#confirmFormSubmit').scrollIntoView();
                    cy.get('#confirmFormSubmit').click();

                    cy.get('.finish-header').contains('Thank you for your order with Demostore!');
                });
            });
        });
    });
});
