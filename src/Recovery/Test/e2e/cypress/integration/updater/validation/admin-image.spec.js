// / <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check image', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/search/media',
            method: 'POST'
        }).as('dataRequest');

        cy.visit('/admin#/sw/media/index');
        cy.login();

        cy.get('.sw-media-library').should('be.visible');
        cy.get('.sw-media-media-item').scrollIntoView();
        cy.get('.sw-media-media-item .sw-media-preview-v2__item')
            .should('have.attr', 'src')
            .and('match', /de-pp-logo/);

        cy.wait('@dataRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    });
});
