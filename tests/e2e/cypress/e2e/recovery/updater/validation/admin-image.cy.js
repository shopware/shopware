// / <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check image', { tags: ['pa-services-settings'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: '/api/search/media',
            method: 'POST',
        }).as('dataRequest');

        cy.login();
        cy.visit('/admin#/sw/media/index');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-media-library').should('be.visible');
        cy.get('.sw-media-media-item').scrollIntoView();
        cy.get('.sw-media-media-item .sw-media-preview-v2__item')
            .should('have.attr', 'src')
            .and('match', /de-pp-logo/);

        cy.wait('@dataRequest').its('response.statusCode').should('equal', 200);
    });
});
