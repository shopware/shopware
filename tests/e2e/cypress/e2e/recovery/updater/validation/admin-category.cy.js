// / <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check category', { tags: ['pa-services-settings'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: '/api/search/category',
            method: 'POST',
        }).as('dataRequest');

        cy.login();
        cy.visit('/admin#/sw/category/index');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-tree-item__label', 'Startseite').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-category-detail-base').should('be.visible');

        cy.get('.smart-bar__header').contains('Startseite');
        cy.wait('@dataRequest').its('response.statusCode').should('equal', 200);
    });
});
