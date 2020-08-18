import CheckoutPageObject from '../../../support/pages/checkout.page-object';
import AccountPageObject from '../../../support/pages/account.page-object';

let product = {};

describe('Checkout: Visual tests', () => {
    beforeEach(() => {
        return cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category')
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
            return cy.createCustomerFixtureStorefront()
        }).then(() => {
            cy.visit('/');
        })
    });

    it('@visual: check appearance of basic checkout workflow', () => {
        const page = new CheckoutPageObject();
        const accountPage = new AccountPageObject();

        if (device.model === 'iphone-6+' && device.orientation === 'portrait') {
            cy.get('.search-toggle-btn').click();
        }

        // Take snapshot for visual testing on desktop
        if (device.model === 'macbook-15') {
            cy.takeSnapshot(`Checkout ${device.model} - Search product`,
                '.header-search-input'
            );
        }

        // Product detail
        cy.get('.header-search-input')
            .type(product.name);
        cy.get('.search-suggest-product-name').contains(product.name);
        cy.get('.search-suggest-product-name').click();


        // Take snapshot for visual testing
        if (device.model === 'macbook-15') {
            cy.takeSnapshot(`Checkout ${device.model} - See product`,
                '.product-detail-buy'
            );
        }

        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas

        // Take snapshot for visual testing on desktop
        if (device.model === 'macbook-15') {
            cy.takeSnapshot(`Checkout ${device.model} - Offcanvas`,
                `${page.elements.offCanvasCart}.is-open`
            );
        }

        cy.get(`${page.elements.cartItem}-label`).contains(product.name);

        // Checkout
        cy.get('.offcanvas-cart-actions .btn-primary').click();

        // Login
        cy.get('.checkout-main').should('be.visible');
        cy.get('.login-collapse-toggle').click();

        // Take snapshot for visual testing on desktop
        if (device.model === 'macbook-15') {
            cy.takeSnapshot(`Checkout ${device.model} - Login`, accountPage.elements.loginCard);
        }

        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get(`${accountPage.elements.loginSubmit} [type="submit"]`).click();

        // Confirm
        cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');

        // Take snapshot for visual testing on desktop
        if (device.model === 'macbook-15') {
            cy.takeSnapshot(`Checkout ${device.model} - Confirm`, '.confirm-tos');
        }

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
        if (device.model === 'macbook-15') {
            cy.takeSnapshot(`Checkout ${device.model} - Finish`, '.finish-header');
        }
    });
});
