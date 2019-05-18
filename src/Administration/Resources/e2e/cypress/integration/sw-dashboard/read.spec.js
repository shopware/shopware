/// <reference types="Cypress" />

import CustomerPageObject from '../../support/pages/module/sw-customer.page-object';

describe('Dashboard: Test first sight of the Administration', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@p read dashboard', () => {
        const page = new CustomerPageObject();

        // Check dashboard in general
        cy.get('.sw-dashboard-card-headline').contains('Welcome');
        cy.get('.sw-dashboard__welcome-content img')
            .should(
                'have.attr',
                'src',
                `${Cypress.config('baseUrl')}/bundles//administration/static/img/dashboard-logo.svg`
            );
        cy.get('.sw-dashboard__documentation').should('be.visible');

        // Check PayPal reference
        cy.get('.sw-dashboard__paypal-icon').should('be.visible');
        cy.get(`.sw-dashboard__paypal ${page.elements.primaryButton}`).should('be.visible');

        // Check Migration reference
        cy.get('.sw-dashboard__migration-content').scrollIntoView();
        cy.get(`.sw-dashboard__migration-content ${page.elements.primaryButton}`).should('be.visible');
    });
});
