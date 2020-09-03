// / <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check customer', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/search/customer',
            method: 'POST'
        }).as('dataRequest');

        cy.visit('/admin#/sw/customer/index');
        cy.login();

        cy.contains('.sw-data-grid__cell--firstName', 'Heino Knopf').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-customer-detail').should('be.visible');

        cy.get('.smart-bar__header').contains('Heino Knopf');

        cy.wait('@dataRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    });
});
