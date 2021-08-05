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
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/search/index/live-search`);
            });
    });

    it('@settings: rebuild search index', () => {
        cy.contains('Search index');
        cy.get('.sw-settings-search__search-index-latest-build').scrollIntoView();
        cy.get('.sw-settings-search__search-index-latest-build').should('be.visible');

        cy.get('.sw-settings-search__search-index-rebuild-button.sw-button').click();
        cy.get('.sw-settings-search__search-index-rebuilding-progress').should('be.visible');
        cy.get('.sw-alert__message').should('be.visible');
        cy.awaitAndCheckNotification('Building product indexes.');

        cy.awaitAndCheckNotification('Product indexes built.');
        cy.get('.sw-settings-search__search-index-rebuilding-progress').should('not.visible');
    });

    it('@settings: should show the warning popup when leaving before indexing process finish', () => {
        cy.contains('Search index');
        cy.get('.sw-settings-search__search-index-latest-build').scrollIntoView();
        cy.get('.sw-settings-search__search-index-latest-build').should('be.visible');

        cy.get('.sw-settings-search__search-index-rebuild-button.sw-button').click();
        cy.get('.sw-settings-search__general-tab').should('be.visible').click();
        cy.get('.sw_settings_search_leave_modal').should('be.visible');

        cy.get('.sw-confirm-modal__button-confirm').should('be.visible').click();
        cy.url().should('contain', '#/sw/settings/search/index/general');
    });
});
