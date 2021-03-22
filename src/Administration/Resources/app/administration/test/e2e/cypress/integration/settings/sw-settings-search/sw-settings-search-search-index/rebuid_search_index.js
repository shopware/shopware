// / <reference types="Cypress" />

describe('Search settings: Search Index', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/search/index`);
            });
    });

    it('@settings: rebuild search index', () => {
        cy.contains('Search index');
        cy.get('.sw-settings-search__search-index-latest-build').scrollIntoView();
        cy.get('.sw-settings-search__search-index-latest-build').should('be.visible');
        cy.get('.sw-alert__message').should('be.visible');

        cy.get('.sw-settings-search__search-index-rebuild-button.sw-button').click();
        cy.get('.sw-settings-search__search-index-rebuilding-progress').should('be.visible');
        cy.awaitAndCheckNotification('Building product indexes.');

        cy.awaitAndCheckNotification('Product indexes built.');
        cy.get('.sw-settings-search__search-index-rebuilding-progress').should('not.visible');
    });
});
