// / <reference types="Cypress" />

import variantProduct from '../../../../../fixtures/variant-product';

describe('Search settings: Live Search', () => {
    beforeEach(() => {
        cy.createProductFixture()
            .then(() => {
                return cy.createProductFixture(variantProduct);
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/search/index/live-search`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: link should open the example popup', { tags: ['pa-system-settings', 'jest'] }, () => {
        cy.contains('Sales Channel live search');

        cy.get('.sw-settings-search-live-search__show-example-link').click();
        cy.get('.sw-modal.sw-settings-search-example-modal').should('be.visible');
    });


    it('@settings: Search for a keyword with no result', { tags: ['pa-system-settings'] }, () => {
        cy.contains('Sales Channel live search');

        // Request we want to wait for later
        cy.intercept({
            url: '/api/_proxy/store-api/*/search',
            method: 'POST',
        }).as('searchKeywords');

        // the input should be disabled
        cy.get('.sw-simple-search-field input').should('be.disabled');

        // select a sales channel to search
        cy.get('.sw-single-select')
            .typeSingleSelect('Storefront', '.sw-single-select');

        cy.get('.sw-simple-search-field input').type('Orange');

        // Verify search keyword
        cy.wait('@searchKeywords')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-settings-search-live-search__no-result').should('be.visible');
    });

    it('@settings: Search for a keyword with result', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: '/api/_proxy/store-api/*/search',
            method: 'POST',
        }).as('searchKeywords');

        cy.get('.sw-single-select').should('be.visible');

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

    it('@settings: Clicking on the search icon to trigger search', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: '/api/_proxy/store-api/*/search',
            method: 'POST',
        }).as('searchKeywords');

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

    it('@settings: Search for a keyword with multiple results', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: '/api/_proxy/store-api/*/search',
            method: 'POST',
        }).as('searchKeywords');

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
