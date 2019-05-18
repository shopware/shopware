import CheckoutPageObject from '../../support/pages/checkout.page-object';
import AccountPageObject from '../../support/pages/account.page-object';

const devices = [{
    model: 'macbook-15',
    orientation: 'portrait'
}, {
    model: 'ipad-2',
    orientation: 'portrait'
}, {
    model: 'iphone-6+',
    orientation: 'portrait'
}, {
    model: 'iphone-6+',
    orientation: 'landscape'
}];
let product = {};

describe('Checkout: Login as customer and run checkout in various viewports', () => {

    beforeEach(() => {
        return cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category')
        }).then(() => {
            return cy.setProductFixtureVisibility('Product name')
        }).then(() => {
            return cy.getRandomProductInformationForCheckout();
        }).then((result) => {
            product = result;
            return cy.createCustomerFixture()
        })
    });

    devices.forEach(device => {
        context(`Checkout on ${device.model} resolution (${device.orientation})`, () => {
            beforeEach(() => {
                // run these tests as if in a desktop
                // browser with a 720p monitor
                cy.viewport(device.model, device.orientation)
            });

            it('run checkout', () => {
                const page = new CheckoutPageObject();
                const accountPage = new AccountPageObject();

                // Product detail
                cy.visit(product.url);
                cy.get('.product-detail-buy .btn-buy').click();

                // Off canvas
                cy.get(`${page.elements.offCanvasCart}.is-open`).should('be.visible');
                cy.get(`${page.elements.cartItem}-label`).contains(product.name);

                // Checkout
                cy.get('.offcanvas-cart-actions .btn-primary').click();

                // Login
                cy.get('.checkout-main').should('be.visible');
                cy.get('.login-collapse-toggle').click();
                cy.get(accountPage.elements.loginCard).should('be.visible');
                cy.get('#loginMail').typeAndCheck('test@example.com');
                cy.get('#loginPassword').typeAndCheck('shopware');
                cy.get(`${accountPage.elements.loginSubmit} [type="submit"]`).click();

                // Confirm
                cy.get('.confirm-tos .card-title').contains('Terms, conditions and cancellation policy');
                cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
                cy.get('.confirm-tos .custom-checkbox label').click(1, 1);
                cy.get('.confirm-address').contains('Pep Eroni');
                cy.get(`${page.elements.cartItem}-details-container ${page.elements.cartItem}-label`).contains(product.name);
                cy.get(`${page.elements.cartItem}-total-price`).contains(product.gross);
                cy.get(`${page.elements.cartItem}-total-price`).contains(product.gross);

                // Finish checkout
                cy.get('#confirmFormSubmit').scrollIntoView();
                cy.get('#confirmFormSubmit').click();
                cy.get('.finish-header').contains('Thank you for your order with Shopware Storefront!');
            });
        });
    });
});
