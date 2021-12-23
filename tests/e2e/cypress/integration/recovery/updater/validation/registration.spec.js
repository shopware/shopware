// / <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check theme compile and caches after update', () => {
        cy.visit('/account/login');
        cy.get('.register-card').should('be.visible');

        cy.get('select[name="salutationId"]').select('Herr');
        cy.get('input[name="firstName"]').type('John');
        cy.get('input[name="lastName"]').type('Doe');

        cy.get('.register-card input[name="email"]').type('john-doe-for-testing@example.com');
        cy.get('.register-card input[name="password"]').type('1234567890');

        cy.get('input[name="billingAddress[street]"]').type('123 Main St');
        cy.get('input[name="billingAddress[zipcode]"]').type('9876');
        cy.get('input[name="billingAddress[city]"]').type('Anytown');

        cy.get('select[name="billingAddress[countryId]"]').select('Deutschland');
        cy.get('select[name="billingAddress[countryStateId]"').should('be.visible');

        cy.get('select[name="billingAddress[countryStateId]"]').select('Bayern');

        cy.get('.register-submit .btn[type="submit"]').click();

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Übersicht');
        });
    });
});
