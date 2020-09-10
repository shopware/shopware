// / <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check product', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/search/product',
            method: 'POST'
        }).as('dataRequest');

        cy.visit('/admin#/sw/product/index');
        cy.login();
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-data-grid__cell--name', 'Travel Pack | Proof Black').should('be.visible');
        cy.contains('.sw-data-grid__cell--name', 'Travel Pack | Proof Black').click();
        cy.get('.smart-bar__header').contains('Travel Pack | Proof Black');

        cy.wait('@dataRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    });
});
