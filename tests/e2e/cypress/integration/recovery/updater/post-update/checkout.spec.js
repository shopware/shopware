// / <reference types="Cypress" />

describe('Validate cart after auto update', () => {

    // dependent pre-update > product.spec.js
    it('@base @catalogue: should complete shopping', ()=>{

        // Login
        cy.visit('/account/login');
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('markus.stein@test.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // Go to cart
        cy.visit('/checkout/cart');

        let productName = 'Product created before update'
        cy.get('.cart-item-details-container [title]').contains(productName);
        cy.get('.cart-item-total-price.col-12.col-md-2.col-sm-4').contains('14,99');
        cy.get('.col-5.checkout-aside-summary-total').contains('14,99');
        cy.get('.checkout-aside-container > :nth-child(3) > .btn').click();

        // Confirm
        cy.get('.confirm-address').contains('Markus Stein');
        cy.get('.cart-item-label').contains(productName);
        cy.get('.cart-item-total-price').scrollIntoView();
        cy.get('.cart-item-total-price').contains('14,99');
        cy.get('.col-5.checkout-aside-summary-total').contains('14,99');

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




