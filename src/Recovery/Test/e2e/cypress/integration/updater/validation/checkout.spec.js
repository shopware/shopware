// / <reference types="Cypress" />

describe('Validate checkout after auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check checkout process after update', () => {
        cy.visit('/');

        // Product detail
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Adidas');
        cy.get('.search-suggest-product-name').contains('Adidas R.Y.V. Hoodie');
        cy.contains('.search-suggest-product-name','Adidas R.Y.V. Hoodie').click();
        cy.get('.product-detail-name').contains('Adidas R.Y.V. Hoodie');
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas.is-open').should('be.visible');
        cy.get('.cart-item-label').contains('Adidas R.Y.V. Hoodie');

        // Checkout
        cy.get('.begin-checkout-btn').click();

        // Login
        cy.get('.checkout-main').should('be.visible');
        cy.get('.login-collapse-toggle').click();
        cy.get('.login-form').should('be.visible');
        cy.get('#loginMail').type('kathie.jaeger@test.com');
        cy.get('#loginPassword').type('shopware');
        cy.get('.login-submit > .btn[type="submit"]').click();

        // Confirm
        cy.get('.confirm-address').contains('Kathie Jäger');
        cy.get('.cart-item-label').contains('Adidas R.Y.V. Hoodie');
        cy.get('.cart-item-total-price').contains('64');
        cy.get('.col-5.checkout-aside-summary-total').contains('64');

        // Set payment and shipping
        cy.get(`#changePaymentForm .payment-method-label`)
            .should('exist')
            .contains('Rechnung')
            .click();

        cy.get(`#changeShippingForm .shipping-method-label`)
            .should('exist')
            .contains('Standard')
            .click();

        // Finish checkout
        cy.get('.confirm-tos .card-title').contains('AGB und Widerrufsbelehrung');
        cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
        cy.get('.confirm-tos .custom-checkbox label').click(1, 1);
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-header').contains(' Vielen Dank für Ihre Bestellung bei Footwear!');
    });
});
