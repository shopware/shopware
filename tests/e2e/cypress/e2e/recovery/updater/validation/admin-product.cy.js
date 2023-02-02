// / <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check product', { tags: ['pa-system-settings'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: '/api/search/product',
            method: 'POST'
        }).as('dataRequest');

        cy.login();
        cy.visit('/admin#/sw/product/index');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-data-grid__cell--name', 'Travel Pack | Proof Black').should('be.visible');
        cy.contains('.sw-data-grid__cell--name', 'Travel Pack | Proof Black').click();
        cy.get('.smart-bar__header').contains('Travel Pack | Proof Black');

        cy.wait('@dataRequest').its('response.statusCode').should('equal', 200);
    });
});
