import CheckoutPageObject from '../../../support/pages/checkout.page-object';
import AccountPageObject from '../../../support/pages/account.page-object';

let product = {};

// TODO See NEXT-6902: Use an own storefront project or make E2E tests independent from bundle
describe('Checkout: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            return cy.setShippingMethodInSalesChannel('Standard');
        }).then(() => {
            return cy.createProductFixture();
        }).then(() => {
            return cy.fixture('product');
        })
            .then((result) => {
                product = result;
                return cy.createCustomerFixture();
            })
            .then(() => {
                cy.visit('/');
            });
    });

    it('@visual: check appearance of basic checkout workflow', () => {
        const page = new CheckoutPageObject();
        const accountPage = new AccountPageObject();

        cy.server();
        cy.route({
            url: '/widgets/checkout/info',
            method: 'get'
        }).as('cartInfo');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot('[Checkout] Search product',
            '.header-search-input',
            {widths: [375, 1920]});

        // Product detail
        cy.get('.header-search-input')
            .type(product.name);
        cy.get('.search-suggest-product-name').contains(product.name);
        cy.get('.search-suggest-product-name').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('[Checkout] See product',
            '.product-detail-buy',
            {widths: [375, 1920]});

        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas').should('be.visible');
        cy.get('.cart-item-price').contains('64');
        cy.get('.offcanvas').should('be.visible');
        cy.contains('Continue shopping').should('be.visible');

        // close offcanvas again
        cy.contains('Continue shopping').click();
        // verify offcanvas is closed
        cy.get('.offcanvas').should('not.be.visible');

        // wait for cart info
        cy.wait('@cartInfo').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.loader').should('not.exist');

        //  total should now be updated
        cy.get('.header-cart-total').scrollIntoView();
        cy.get('.header-cart-total').contains('64');
        cy.get('.header-cart-total').click();
        cy.get('.offcanvas').should('be.visible');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot('[Checkout] Offcanvas',
            `${page.elements.offCanvasCart}.is-open`,
            {widths: [375, 1920]});

        cy.get(`${page.elements.cartItem}-label`).contains(product.name);

        // Checkout
        cy.get('.offcanvas-cart-actions .btn-primary').click();

        // Login
        cy.get('.checkout-main').should('be.visible');
        cy.get('.login-collapse-toggle').click();

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot('[Checkout] Login', accountPage.elements.loginCard, {widths: [375, 1920]});

        cy.get('#loginMail').type('test@example.com');
        cy.get('#loginPassword').type('shopware');
        cy.get(`${accountPage.elements.loginSubmit} [type="submit"]`).click();

        // Confirm
        cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot('[Checkout] Confirm', '.confirm-tos', {widths: [375, 1920]});

        cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
        cy.get('.confirm-tos .custom-checkbox label').click(1, 1);
        cy.get('.confirm-address').contains('Pep Eroni');
        cy.get(`${page.elements.cartItem}-details-container ${page.elements.cartItem}-label`).contains(product.name);
        cy.get(`${page.elements.cartItem}-total-price`).contains(product.price[0].gross);
        cy.get(`${page.elements.cartItem}-total-price`).contains(product.price[0].gross);

        // Finish checkout
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot('[Checkout] Finish', '.finish-header', {widths: [375, 1920]});
    });
});
