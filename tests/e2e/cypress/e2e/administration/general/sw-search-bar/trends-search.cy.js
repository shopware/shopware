/// <reference types="Cypress" />

describe('Search bar: Check search by frequently used and recently searched',() => {
    it('@searchBar search recently searched', { tags: ['pa-services-settings'] }, () => {
        cy.createProductFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
                cy.get('.sw-dashboard-index__welcome-title').should('be.visible');
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });

        cy.get('input.sw-search-bar__input')
            .click();
        cy.get('input.sw-search-bar__input')
            .type('Product');
        cy.get('.sw-search-bar__results-list')
            .should('be.visible');
        cy.contains('.sw-search-bar-item', 'Product name')
            .should('be.visible');
        cy.contains('.sw-search-bar-item', 'Product name')
            .click();

        cy.contains('.smart-bar__header', 'Product name');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`, { timeout: 30000 });
        cy.get('.sw-dashboard-index__welcome-title').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input.sw-search-bar__input')
            .clear()
            .click();

        cy.get('.sw-search-bar__results-column').eq(1)
            .contains('.sw-search-bar__results-column-header', 'Recently searched');
        cy.get('.sw-search-bar__results-column').eq(1).get('.sw-search-bar-item')
            .should('contain', 'Product name');
    });
});
