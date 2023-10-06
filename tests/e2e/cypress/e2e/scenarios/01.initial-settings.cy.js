/// <reference types="Cypress" />

describe('sales channel: set and validate initial settings', () => {
    it('@package: should set and validate default sales channel settings', { tags: ['pa-sales-channels'] }, () => {
        cy.visit(`${Cypress.env('admin')}#/sw/settings/listing/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'settings/listing/index');
        cy.setSalesChannel(Cypress.env('storefrontName'));
        cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'settings/shipping/index');
        cy.setShippingMethod('Standard', 5, 4);

        cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/overview`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'settings/payment/overview');
        cy.setPaymentMethod('Cash on delivery');
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'dashboard/index');
        cy.goToSalesChannelDetail(Cypress.env('storefrontName'))
            .selectCountryForSalesChannel('Netherlands')
            .selectLanguageForSalesChannel('Dutch')
            .selectPaymentMethodForSalesChannel('Cash on delivery')
            .selectShippingMethodForSalesChannel('Standard')
            .selectCurrencyForSalesChannel('Euro');

        // Logout
        cy.get('.sw-admin-menu__user-actions-toggle').should('be.visible').click();
        cy.get('.sw-admin-menu__logout-action').should('be.visible').click();
        cy.contains('.sw-login__content-headline', 'Shopware').should('be.visible');
    });
});
