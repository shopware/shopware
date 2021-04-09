/// <reference types="Cypress" />

describe('Search: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createLanguageFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/search/index`);
            });
    });

    it('@base @settings: should be not infinity loading when switch to new language', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/*`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-language-switch__select').typeSingleSelectAndCheck(
            'Philippine',
            '.sw-language-switch__select'
        );

        // Verify getData
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-loader').should('not.exist');
    });
});
