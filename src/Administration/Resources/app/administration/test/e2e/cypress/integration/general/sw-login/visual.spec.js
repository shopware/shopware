/// <reference types="Cypress" />

describe('Login: Visual tests', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.visit(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of basic login workflow', () => {
        // Remove login image if percy is used
        if(!Cypress.env('usePercy')) {

            // Change background image of the element to ensure consistent snapshots
            cy.changeElementStyling(
                '.sw-login__image',
                `background-image: url("${Cypress.config('baseUrl')}/bundles/administration/static/img/sw-login-background.png")`
            );

            // Take snapshot for visual testing
            cy.takeSnapshot('[Login] Administration', '.sw-login');
        }
    });
});
