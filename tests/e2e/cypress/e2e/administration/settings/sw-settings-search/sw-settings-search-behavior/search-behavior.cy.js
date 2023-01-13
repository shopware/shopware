// / <reference types="Cypress" />

describe('Product Search: Test crud operations', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/search/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@settings: create search configuration for search behaviour', { tags: ['pa-system-settings'] }, () => {
        // Request we want to wait for later

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-search-config/*`,
            method: 'PATCH',
        }).as('saveData');

        // Change value of Minimal search term length
        cy.get('.sw-settings-search__search-behaviour-term-length input').clear().type('10');

        // Check for Or Behaviour option
        cy.get('input[type="radio"]#sw-field--searchBehaviourConfigs-andLogic-1').check({ force: true });

        cy.get('.sw-settings-search__button-save').click();

        // Verify save search config method
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        cy.awaitAndCheckNotification('Configuration saved.');
    });

    it('@settings: create search configuration for search behaviour bases on another language', { tags: ['pa-system-settings'] }, () => {
        // Request we want to wait for later

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-search-config/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-search-config/*`,
            method: 'PATCH',
        }).as('saveData');

        // Switch language to Deutsch
        cy.contains('.sw-language-switch__select .sw-entity-single-select__selection-text', 'English');
        cy.get('.smart-bar__content .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        // poor assertion to check if there is more than 1 language
        cy.get('.sw-select-result-list__item-list .sw-select-result')
            .should('have.length.greaterThan', 1);
        cy.contains('.sw-select-result-list__item-list .sw-select-result', 'Deutsch').click();

        // change value of Minimal search term length
        cy.get('.sw-settings-search__search-behaviour-term-length input').clear().type('19');

        // Check for Or Behaviour option
        cy.get('input[type="radio"]#sw-field--searchBehaviourConfigs-andLogic-1').check({ force: true });

        cy.get('.sw-settings-search__button-save').click();

        // Verify save search config method
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        cy.contains('.sw-alert__message', 'Configuration saved');
    });
});
