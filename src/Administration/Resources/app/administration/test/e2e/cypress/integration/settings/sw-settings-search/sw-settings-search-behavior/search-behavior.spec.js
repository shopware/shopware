// / <reference types="Cypress" />

import CategoryPageObject from '../../../../support/pages/module/sw-category.page-object';

describe('Product Search: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/search/index`);
            });
    });

    it('@settings: create search configuration for search behaviour', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/product-search-config/*',
            method: 'patch'
        }).as('saveData');

        // Change value of Minimal search term length
        cy.get('.sw-settings-search__search-behaviour-term-length input').clear().type('10');

        // Check for Or Behaviour option
        cy.get('input[type="radio"]#sw-field--searchBehaviourConfigs-andLogic-1').check({ force: true });

        cy.get('.sw-settings-search__button-save').click();

        // Verify save search config method
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.awaitAndCheckNotification('Configuration saved.');
    });

    it('@settings: create search configuration for search behaviour bases on another language', () => {
        const page = new CategoryPageObject();
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/product-search-config/*',
            method: 'patch'
        }).as('saveData');

        // Choose German language
        page.changeTranslation('Deutsch', 0);

        // change value of Minimal search term length
        cy.get('.sw-settings-search__search-behaviour-term-length input').clear().type('19');

        // Check for Or Behaviour option
        cy.get('input[type="radio"]#sw-field--searchBehaviourConfigs-andLogic-1').check({ force: true });

        cy.get('.sw-settings-search__button-save').click();

        // Verify save search config method
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.awaitAndCheckNotification('Configuration saved.');
    });
});
