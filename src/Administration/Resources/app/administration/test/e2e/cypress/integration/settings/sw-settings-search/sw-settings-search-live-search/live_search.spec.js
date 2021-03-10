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
                cy.onlyOnFeature('FEATURE_NEXT_10552');
            });
    });

    it('@settings: link should go back to general tab', () => {
        cy.contains('Storefront live search');

        cy.contains('Rebuild search index');
        cy.get('.sw-settings-search-live-search__rebuild-link').click();
        cy.location('hash').should('contain', '#/sw/settings/search/index/general');
    });

    it('@settings: Search for a keyword with no result', () => {
        cy.contains('Storefront live search');

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/_proxy/store-api/*/search',
            method: 'post'
        }).as('searchKeywords');

        // the input should be disabled
        cy.get('.sw-simple-search-field input').should('be.disabled');

        // select a sales channel to search
        cy.get('.sw-single-select')
            .typeSingleSelect('Storefront', '.sw-single-select');

        cy.get('.sw-simple-search-field input').type('Orange');

        // Verify search keyword
        cy.wait('@searchKeywords').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-settings-search-live-search__no-result').should('be.visible');
        });
    });

    it('@settings: Search for a keyword with result', () => {
        cy.server();
        cy.route({
            url: '/api/_proxy/store-api/*/search',
            method: 'post'
        }).as('searchKeywords');

        // select a sales channel to search
        cy.get('.sw-single-select')
            .typeSingleSelect('Storefront', '.sw-single-select');

        cy.get('.sw-simple-search-field input').type('variant');

        // Verify search keyword
        cy.wait('@searchKeywords').then((xhr) => {
            expect(xhr).to.have.property('status', 200);

            // should show the data grid result
            cy.get('.sw-settings-search-live-search__grid-result').should('be.visible');
            // should show the highlight keywords
            cy.get('.sw-settings-search-live-search-keyword__highlight').should('be.visible');
        });
    });


    it('@settings: Search for a keyword with multiple results', () => {
        cy.server();
        cy.route({
            url: '/api/_proxy/store-api/*/search',
            method: 'post'
        }).as('searchKeywords');

        // select a sales channel to search
        cy.get('.sw-single-select')
            .typeSingleSelect('Storefront', '.sw-single-select');

        cy.get('.sw-simple-search-field input').type('product');

        // Verify search keyword
        cy.wait('@searchKeywords').then((xhr) => {
            expect(xhr).to.have.property('status', 200);

            // should show the data grid result
            cy.get('.sw-settings-search-live-search__grid-result').should('be.visible');
            // should show the highlight keywords
            cy.get('.sw-settings-search-live-search-keyword__highlight').should('be.visible');

            cy.get('.sw-data-grid__body').find('tr').should('have.length', 2);
        });
    });
});
