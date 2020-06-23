// / <reference types="Cypress" />

describe('Validate order with edited status after auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */
    before(() => {
        cy.visit('/admin');

        cy.get('.sw-login__content').should('be.visible');
        cy.get('#sw-field--username').clear().type(Cypress.env('user'));
        cy.get('#sw-field--password').clear().type(Cypress.env('pass'));
        cy.get('.sw-button__content').click();

        cy.prepareMinimalUpdate(Cypress.env('expectedVersion')).then(() => {
            cy.runMinimalAutoUpdate(Cypress.env('expectedVersion'));
        });
    });

    it('@update: Check order with pre-edited status', () => {

    });
});
