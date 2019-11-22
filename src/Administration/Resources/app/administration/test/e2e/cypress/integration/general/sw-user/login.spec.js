// / <reference types="Cypress" />

describe('Login: Test login', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.visit(Cypress.env('admin'));
            });
    });

    it('@general: login as admin user', () => {
        cy.login('admin');
    });
});
