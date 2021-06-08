/// <reference types="Cypress" />

describe('Profile: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi()
            }).then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/profile/index`);
            });
    });

    it('@visual: check appearance of basic profile workflow', () => {
        // Take snapshot for visual testing
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Profile] Detail', '.sw-profile__card');
    });
});
