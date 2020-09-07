// / <reference types="Cypress" />

describe('User: Test general user values', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}`);
            });
    });

    it('@base @general: check if user title is set correctly', () => {
        cy.get('.sw-admin-menu__user-type').contains('Administrator');
    });
});
