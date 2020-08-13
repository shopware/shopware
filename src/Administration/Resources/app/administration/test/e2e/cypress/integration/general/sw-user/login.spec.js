/// <reference types="Cypress" />

describe('Login: Test login', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.visit(Cypress.env('admin'));
            });
    });

    it('@base @general: login as admin user', () => {
        // Remove login image if percy is used
        if(!Cypress.env('usePercy')) {
            const backgroundImageStyle = `background-image: url("${Cypress.config('baseUrl')}/bundles/administration/static/img/sw-login-background.png")`;

            cy.get('.sw-login__image')
                .invoke('attr', 'style', backgroundImageStyle)
                .should('have.attr', 'style', backgroundImageStyle)

            // Take snapshot for visual testing
            cy.takeSnapshot('Login', '.sw-login');
        }

        cy.login('admin');
    });
});
