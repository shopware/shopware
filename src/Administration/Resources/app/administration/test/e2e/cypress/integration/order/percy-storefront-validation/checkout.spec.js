import CheckoutPageObject from '../../../support/pages/checkout.page-object';
import AccountPageObject from '../../../support/pages/account.page-object';

let product = {};

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

        cy.intercept({
            url: '/widgets/checkout/info',
            method: 'GET'
        }).as('cartInfo');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot('[Checkout] Search product',
            '.header-search-input',
            { widths: [375, 1920] });

        // Product detail
        cy.get('.header-search-input')
            .type(product.name);
        cy.get('.search-suggest-product-name').contains(product.name);
        cy.get('.search-suggest-product-name').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('[Checkout] See product',
            '.product-detail-buy',
            { widths: [375, 1920] });

        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas').should('be.visible');
        cy.wait('@cartInfo')
            .its('response.statusCode').should('equal', 200);
        cy.get('.loader').should('not.exist');

        // Take snapshot for visual testing on desktop
        cy.contains('.header-cart-total', '64').should('exist');
        cy.contains('.cart-item-price', '64').should('be.visible');

        cy.changeElementStyling(
            '.header-search',
            'visibility: hidden'
        );
        cy.get('.header-search')
            .should('have.css', 'visibility', 'hidden');
        cy.changeElementStyling(
            '#accountWidget',
            'visibility: hidden'
        );
        cy.get('#accountWidget')
            .should('have.css', 'visibility', 'hidden');
        cy.get('.loader').should('not.exist');

        cy.takeSnapshot('[Checkout] Offcanvas',
            `${page.elements.offCanvasCart}.is-open`,
            { widths: [375, 1920] });

        cy.get(`${page.elements.cartItem}-label`).contains(product.name);

        // Checkout
        cy.get('.offcanvas-cart-actions .btn-primary').click();

        // Login
        cy.get('.checkout-main').should('be.visible');
        cy.get('.login-collapse-toggle').click();
        cy.get('#loginMail').should('be.visible');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot('Checkout - Login', accountPage.elements.loginCard, { widths: [375, 1920] });

        cy.get('#loginMail').type('test@example.com');
        cy.get('#loginPassword').type('shopware');
        cy.get(`${accountPage.elements.loginSubmit} [type="submit"]`).click();

        // Confirm
        cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot('Checkout - Confirm', '.confirm-tos', { widths: [375, 1920] });

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
        cy.takeSnapshot('Checkout - Finish', '.finish-header', { widths: [375, 1920] });
    });
});
