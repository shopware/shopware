/// <reference types="Cypress" />

describe('Snippets: Visual testing', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createSnippetFixture();
            })
            .then(() => {
                cy.fixture('snippet').as('testSnippet');
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of snippet module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/snippet-set`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-snippet').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-grid').should('be.visible');
        cy.takeSnapshot('[Snippets] Listing of snippet sets',
            '.sw-settings-snippet-set-list');

        cy.contains('.sw-grid__cell-content a', 'BASE de-DE').click();
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('[Snippets] Snippet listing',
            '.sw-import-export-view-profiles__listing');

        cy.contains('.sw-data-grid__cell-content a', 'aWonderful.customSnip').click();
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Snippets] Detail', '.sw-settings-snippet-detail');
    });
});
