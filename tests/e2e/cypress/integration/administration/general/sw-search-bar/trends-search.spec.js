/// <reference types="Cypress" />

describe('Search bar: Check search by frequently used and recently searched',() => {
    beforeEach(() => {
        cy.onlyOnFeature('FEATURE_NEXT_6040');
        cy.loginViaApi();
    });

    it('@searchBar search frequently used modules', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/increment/user_activity?*`,
            method: 'GET'
        }).as('getFrequentlyUsed');

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);

        cy.get('input.sw-search-bar__input').type('products');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('Products')
            .click();

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`, { timeout: 30000 });
        cy.get('.sw-dashboard')
            .should('exist');
        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').click();

        cy.wait('@getFrequentlyUsed')
            .its('response.statusCode')
            .should('equal', 200)

        const firstColumn = cy.get('.sw-search-bar__results-column').eq(0);

        firstColumn.get('.sw-search-bar__results-column-header')
            .contains('Frequently used');
        firstColumn.get('.sw-search-bar-item')
            .should('contain', 'Products')
            .and('contain', 'Dashboard');
    })

    it('@searchBar search recently searched', () => {
        cy.createProductFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('input.sw-search-bar__input').type('Product');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('Product name')
            .click();

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`, { timeout: 30000 });
        cy.get('.sw-dashboard')
            .should('exist');
        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input')
            .clear()
            .click();

        const secondColumn = cy.get('.sw-search-bar__results-column').eq(1);

        secondColumn.get('.sw-search-bar__results-column-header')
            .contains('Recently searched');
        secondColumn.get('.sw-search-bar-item')
            .should('contain', 'Product name');
    })
});
