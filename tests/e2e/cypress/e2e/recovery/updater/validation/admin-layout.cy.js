// / <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check layout', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: '/api/search/cms-page',
            method: 'POST',
        }).as('dataRequest');

        cy.login();
        cy.visit('/admin#/sw/cms/index');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-cms-list-item', 'Beste Produkte Landingpage').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-detail').should('be.visible');

        cy.get('.sw-cms-detail__page-name').contains('Beste Produkte Landingpage');

        cy.wait('@dataRequest').its('response.statusCode').should('equal', 200);
    });
});
