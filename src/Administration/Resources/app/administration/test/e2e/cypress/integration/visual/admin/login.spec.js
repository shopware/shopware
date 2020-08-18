/// <reference types="Cypress" />

describe('Login: Visual tests', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.visit(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of basic login workflow', () => {
        // Remove login image if percy is used
        if(!Cypress.env('usePercy')) {
            cy.changeElementStyling(
                '.sw-login__image',
                `background-image: url("${Cypress.config('baseUrl')}/bundles/administration/static/img/sw-login-background.png")`
            );

            // Take snapshot for visual testing
            cy.takeSnapshot('Login', '.sw-login');
        }
    });
});
