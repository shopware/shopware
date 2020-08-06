/// <reference types="Cypress" />

describe('Login: Test login', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.visit(Cypress.env('admin'));
            });
    });

    it('@base @general: login as admin user', () => {
        // Take snapshot for visual testing
        cy.takeSnapshot('Login', '.sw-login');

        cy.login('admin');
    });
});
