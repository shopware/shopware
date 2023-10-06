/// <reference types="Cypress" />

describe('Login: Test login', () => {
    beforeEach(() => {
        cy.clearCookies();
        cy.clearCookie('bearerAuth');
        cy.clearCookie('refreshBearerAuth');
        cy.setLocaleToEnGb()
            .then(() => {
                cy.visit(Cypress.env('admin'));
            });
    });

    it('@base @general: login as admin user', { tags: ['ct-admin'] }, () => {
        cy.login('admin');
    });
});
