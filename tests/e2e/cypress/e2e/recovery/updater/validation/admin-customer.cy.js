// / <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check customer', { tags: ['pa-system-settings'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: '/api/search/customer',
            method: 'POST'
        }).as('dataRequest');

        cy.login();
        cy.visit('/admin#/sw/customer/index');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-data-grid__cell--firstName', 'Knopf').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-customer-detail').should('be.visible');

        cy.get('.smart-bar__header').contains('Heino Knopf');
        cy.wait('@dataRequest').its('response.statusCode').should('equal', 200);
    });
});
