/// <reference types="Cypress" />

describe('Search bar: Check search by frequently used and recently searched',() => {
    beforeEach(() => {
        cy.loginViaApi();
    });

    // NEXT-20024
    it('@searchBar search frequently used modules', { tags: ['quarantined', 'pa-system-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/increment/user_activity?*`,
            method: 'GET'
        }).as('getFrequentlyUsed');

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.get('.sw-dashboard-index__welcome-title').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input.sw-search-bar__input').click();
        cy.get('.sw-search-bar__types-header-entity')
            .should('be.visible');
        cy.get('input.sw-search-bar__input').type('products');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.contains('.sw-search-bar-item', 'Products')
            .should('be.visible')
            .click();

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`, { timeout: 30000 });
        cy.get('.sw-dashboard-index__welcome-title').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input.sw-search-bar__input').click();

        cy.wait('@getFrequentlyUsed')
            .its('response.statusCode')
            .should('equal', 200)

        const firstColumn = cy.get('.sw-search-bar__results-column').eq(0);

        firstColumn.contains('.sw-search-bar__results-column-header', 'Frequently used');
        firstColumn.get('.sw-search-bar-item')
            .should('contain', 'Products')
            .and('contain', 'Dashboard');
    })

    it('@searchBar search recently searched', { tags: ['pa-system-settings'] }, () => {
        cy.createProductFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
                cy.get('.sw-dashboard-index__welcome-title').should('be.visible');
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });

        cy.get('input.sw-search-bar__input')
            .click();
        cy.get('.sw-search-bar__types-header-entity')
            .should('be.visible');
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

        const secondColumn = cy.get('.sw-search-bar__results-column').eq(1);

        secondColumn.contains('.sw-search-bar__results-column-header', 'Recently searched');
        secondColumn.get('.sw-search-bar-item')
            .should('contain', 'Product name');
    })
});
