/// <reference types="Cypress" />

describe('sales channel: set and validate initial settings', () => {

    before(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        });
    });

    it('@package: should set and validate default sales channel settings', () => {

        cy.visit(`${Cypress.env('admin')}#/sw/settings/listing/index`);
        cy.url().should('include', 'settings/listing/index');
        cy.setSalesChannel('E2E install test');
        cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
        cy.url().should('include', 'settings/shipping/index');
        cy.setShippingMethod('Standard', 5, 4);
        cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/index`);
        cy.url().should('include', 'settings/payment/index');
        cy.setPaymentMethod('Cash on delivery');
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.url().should('include', 'dashboard/index');
        cy.selectCountry('E2E install test','Netherlands');
        cy.selectLanguage('E2E install test', 'Dutch');
        cy.selectPayment('E2E install test', 'Cash on delivery');
        cy.selectShipping('E2E install test', 'Standard');
        cy.selectCurrency('E2E install test','Euro');

        // Logout
        cy.get('.sw-admin-menu__user-actions-toggle').should('be.visible').click();
        cy.get('.sw-admin-menu__logout-action').should('be.visible').click();
        cy.contains('.sw-login__content-headline', 'Shopware').should('be.visible');
    });

});
