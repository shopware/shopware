/// <reference types="Cypress" />

import CategoryPageObject from '../../../support/pages/module/sw-category.page-object';

describe('Product Search: Test crud operations', () => {
    before(() => {
        cy.onlyOnFeature('FEATURE_NEXT_10552');
    });

    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/search/index`);
            });
    });

    it('@base @settings: create search configuration for search behaviour', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/product-search-config/*',
            method: 'patch'
        }).as('saveData');

        // Make sure that everything need to load first.
        cy.wait(3000);

        // Change value of Minimal search term length
        cy.get('.sw-settings-search__search-behaviour-term-length input').clear().type('10');

        // Check for Or Behaviour option
        cy.get('input[type="radio"]#sw-field--searchBehaviourConfigs-andLogic-1').check();

        cy.get('.sw-settings-search__button-save').click();

        // Verify save search config method
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });

    it('@base @settings: create search configuration for search behaviour bases on another language', () => {
        const page = new CategoryPageObject();
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/product-search-config/*',
            method: 'patch'
        }).as('saveData');

        // Make sure that everything need to load first.
        cy.wait(3000);

        // Choose German language
        page.changeTranslation('Deutsch', 0);

        // change value of Minimal search term length
        cy.get('.sw-settings-search__search-behaviour-term-length input').clear().type('19');

        // Check for Or Behaviour option
        cy.get('input[type="radio"]#sw-field--searchBehaviourConfigs-andLogic-1').check();

        cy.get('.sw-settings-search__button-save').click();

        // Verify save search config method
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });
});
