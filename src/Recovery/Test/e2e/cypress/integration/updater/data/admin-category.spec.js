// / <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check category', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/search/category',
            method: 'POST'
        }).as('dataRequest');

        cy.visit('/admin#/sw/category/index');
        cy.login();

        cy.contains('.sw-tree-item__label', 'Startseite').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-category-detail-base').should('be.visible');

        cy.get('.smart-bar__header').contains('Startseite');

        cy.wait('@dataRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
    });
});
