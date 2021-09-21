// / <reference types="Cypress" />

import variantProduct from '../../../../fixtures/variant-product';

describe('Search settings: Live Search', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createProductFixture(variantProduct);
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/search/index/live-search`);
            });
    });

    it('@settings: link should open the example popup', () => {
        cy.contains('Sales Channel live search');

        cy.get('.sw-settings-search-live-search__show-example-link').click();
        cy.get('.sw-modal.sw-settings-search-example-modal').should('be.visible');
    });


    it('@settings: Search for a keyword with no result', () => {
        cy.contains('Sales Channel live search');

        // Request we want to wait for later
        cy.intercept({
            url: '/api/_proxy/store-api/*/search',
            method: 'POST'
        }).as('searchKeywords');

        // the input should be disabled
        cy.get('.sw-simple-search-field input').should('be.disabled');

        // typeSingleSelect seems to be buggy and require this delay
        cy.wait(500);
        // select a sales channel to search
        cy.get('.sw-single-select')
            .typeSingleSelect('Storefront', '.sw-single-select');

        cy.get('.sw-simple-search-field input').type('Orange');

        // Verify search keyword
        cy.wait('@searchKeywords')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-settings-search-live-search__no-result').should('be.visible');
    });

    it('@settings: Search for a keyword with result', () => {
        cy.intercept({
            url: '/api/_proxy/store-api/*/search',
            method: 'POST'
        }).as('searchKeywords');

        cy.get('.sw-single-select').should('be.visible');

        // typeSingleSelect seems to be buggy and require this delay, @see NEXT-17177
        cy.wait(500);
        // select a sales channel to search
        cy.get('.sw-single-select')
            .typeSingleSelect('Storefront', '.sw-single-select');

        cy.get('.sw-simple-search-field input').type('variant');

        // Verify search keyword
        cy.wait('@searchKeywords')
            .its('response.statusCode').should('equal', 200);
        // should show the data grid result
        cy.get('.sw-settings-search-live-search__grid-result').should('be.visible');
        // should show the highlight keywords
        cy.get('.sw-settings-search-live-search-keyword__highlight').should('be.visible');
    });

    it('@settings: Clicking on the search icon to trigger search', () => {
        cy.intercept({
            url: '/api/_proxy/store-api/*/search',
            method: 'POST'
        }).as('searchKeywords');

        // typeSingleSelect seems to be buggy and require this delay, @see NEXT-17177
        cy.wait(500);
        cy.get('.sw-single-select').should('be.visible');

        // select a sales channel to search
        cy.get('.sw-single-select')
            .typeSingleSelect('Storefront', '.sw-single-select');

        cy.get('.sw-simple-search-field input').type('variant');
        cy.get('.sw-settings-search-live-search__search-icon').click();

        // Verify search keyword
        cy.wait('@searchKeywords')
            .its('response.statusCode').should('equal', 200);

        // should show the data grid result
        cy.get('.sw-settings-search-live-search__grid-result').should('be.visible');
        // should show the highlight keywords
        cy.get('.sw-settings-search-live-search-keyword__highlight').should('be.visible');
    });

    it('@settings: Search for a keyword with multiple results', () => {
        cy.intercept({
            url: '/api/_proxy/store-api/*/search',
            method: 'POST'
        }).as('searchKeywords');

        // typeSingleSelect seems to be buggy and require this delay, @see NEXT-17177
        cy.wait(500);
        // select a sales channel to search
        cy.get('.sw-single-select')
            .typeSingleSelect('Storefront', '.sw-single-select');

        cy.get('.sw-simple-search-field input').type('product');

        // Verify search keyword
        cy.wait('@searchKeywords')
            .its('response.statusCode').should('equal', 200);

        // should show the data grid result
        cy.get('.sw-settings-search-live-search__grid-result').should('be.visible');
        // should show the highlight keywords
        cy.get('.sw-settings-search-live-search-keyword__highlight').should('be.visible');

        cy.get('.sw-data-grid__body').find('tr').should('have.length', 2);
    });
});
