/// <reference types="Cypress" />

describe('Sales Channel: set initial settings', () => {

    beforeEach(() => {
        cy.visit(`${Cypress.env('admin')}#/login`);
        cy.login();
    });

    it('@package: login & logout',()=>{
        cy.get('.sw-admin-menu__user-actions-toggle').click();
        cy.get('.sw-admin-menu__logout-action').should('be.visible').click();
        cy.contains('.sw-login__content-headline', 'Log in to your Shopware store')
    });

    it('@package: set default settings for sales channels',()=>{
        cy.setSalesChannel('E2E install test');
        cy.setSalesChannel('Headless');
        cy.setShippingMethod('Standard', 5, 4);
        cy.setShippingMethod('Express', 10, 8);
        cy.setPaymentMethod('Cash on delivery');
        cy.setPaymentMethod('Invoice');
        cy.selectCountry('E2E install test','Netherlands');
        cy.selectLanguage('E2E install test', 'Dutch');
        cy.selectPayment('E2E install test', 'Cash on delivery');
        cy.selectShipping('E2E install test', 'Standard');
        cy.selectCurrency('E2E install test','Euro');
        cy.selectCountry('Headless','Germany');
        cy.selectLanguage('Headless', 'Deutsch');
        cy.selectPayment('Headless', 'Invoice');
        cy.selectShipping('Headless', 'Express');
    });

});
