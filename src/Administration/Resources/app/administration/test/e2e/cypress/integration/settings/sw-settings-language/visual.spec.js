/// <reference types="Cypress" />

describe('Language: Visual testing', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@base @visual: check appearance of language module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/language`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-language').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.takeSnapshot('[Language] Listing', '.sw-settings-language-list');

        cy.contains('.sw-data-grid__cell--name a', 'English').click();
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Language] Details', '.sw-settings-language-detail');
    });
});
