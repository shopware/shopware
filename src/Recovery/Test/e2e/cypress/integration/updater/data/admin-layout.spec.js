// / <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check layout', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/search/cms-page',
            method: 'POST'
        }).as('dataRequest');

        cy.visit('/admin#/sw/cms/index');
        cy.login();

        cy.contains('.sw-cms-list-item', 'Beste Produkte Landingpage').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-detail').should('be.visible');

        cy.get('.sw-cms-detail__page-name').contains('Beste Produkte Landingpage');

        cy.wait('@dataRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    });
});
