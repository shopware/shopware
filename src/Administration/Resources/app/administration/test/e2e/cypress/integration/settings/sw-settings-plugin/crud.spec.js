/// <reference types="Cypress" />

describe('Plugin: Plugin manager', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/plugin/index/list`);
            });
    });

    it.skip('@settings: license list shows empty state', () => {
        // Visit license list
        cy.get('a[href="#/sw/plugin/index/licenses"]').click();

        cy.get('.sw-empty-state').should('be.visible');
    });
});
