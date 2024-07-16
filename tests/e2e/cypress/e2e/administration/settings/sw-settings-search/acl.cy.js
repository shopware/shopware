/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */
import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Search: Test ACL privileges', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
    });

    it('@settings: read search', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product-search-config`,
            method: 'POST',
        }).as('getProductSearchConfig');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product-search-config-field`,
            method: 'POST',
        }).as('getProductSearchConfigField');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/increment/user_activity`,
            method: 'POST',
        }).as('getUserActivity');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'POST',
        }).as('getSalesChannel');

        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-tabs').should('be.visible');
        cy.get('.sw-tabs').contains('.sw-tabs-item:first-child', 'General');
        cy.get('.sw-tabs').contains('.sw-tabs-item:nth-child(2)', 'Live search');

        cy.get('.sw-tabs').contains('.sw-tabs-item:first-child', 'General').click();

        cy.wait('@getProductSearchConfig').its('response.statusCode').should('equal', 200);
        cy.wait('@getProductSearchConfigField').its('response.statusCode').should('equal', 200);

        cy.get('.sw-settings-search-search-behaviour').scrollIntoView();
        cy.get('.sw-settings-search-search-behaviour').contains('.sw-card__title', 'Search behaviour');

        cy.get('.sw-settings-search-searchable-content').scrollIntoView();
        cy.get('.sw-settings-search-searchable-content').contains('.sw-card__title', 'Searchable content');

        cy.get('.sw-settings-search-excluded-search-terms').scrollIntoView();
        cy.get('.sw-settings-search-excluded-search-terms').contains('.sw-card__title', 'Excluded search terms');

        cy.get('.sw-tabs').contains('.sw-tabs-item:nth-child(2)', 'Live search').click();

        cy.wait('@getUserActivity').its('response.statusCode').should('equal', 200);
        cy.wait('@getSalesChannel').its('response.statusCode').should('equal', 200);

        cy.get('.sw-settings-search-live-search').scrollIntoView();
        cy.get('.sw-settings-search-live-search').contains('.sw-card__title', 'Sales Channel live search');
    });

    it('@settings: edit search', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product-search-config`,
            method: 'POST',
        }).as('getProductSearchConfig');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product-search-config-field`,
            method: 'POST',
        }).as('getProductSearchConfigField');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-search-config/*`,
            method: 'PATCH',
        }).as('updateProductSearchConfig');

        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'viewer',
            },
            {
                key: 'product_search_config',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-tabs').should('be.visible');
        cy.get('.sw-tabs').contains('.sw-tabs-item:first-child', 'General').click();

        cy.wait('@getProductSearchConfig').its('response.statusCode').should('equal', 200);
        cy.wait('@getProductSearchConfigField').its('response.statusCode').should('equal', 200);

        cy.get('.sw-settings-search-search-behaviour').scrollIntoView();
        cy.get('.sw-settings-search-search-behaviour').contains('.sw-card__title', 'Search behaviour');

        cy.get('input[name="sw-field--searchBehaviourConfigs-minSearchLength"]').clearTypeAndCheck(5);

        cy.get('.sw-settings-search__button-save').click();
        cy.wait('@updateProductSearchConfig').its('response.statusCode').should('within', 200, 204);
        cy.awaitAndCheckNotification('Configuration saved.');

        cy.get('input[name="sw-field--searchBehaviourConfigs-minSearchLength"]').should('have.value', 5);
    });

    it('@settings: create search', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product-search-config`,
            method: 'POST',
        }).as('getProductSearchConfig');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product-search-config-field`,
            method: 'POST',
        }).as('getProductSearchConfigField');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-search-config/*`,
            method: 'PATCH',
        }).as('updateProductSearchConfig');

        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'viewer',
            },
            {
                key: 'product_search_config',
                role: 'editor',
            },
            {
                key: 'product_search_config',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-tabs').should('be.visible');
        cy.get('.sw-tabs').contains('.sw-tabs-item:first-child', 'General').click();

        cy.wait('@getProductSearchConfig').its('response.statusCode').should('equal', 200);
        cy.wait('@getProductSearchConfigField').its('response.statusCode').should('equal', 200);

        cy.get('.sw-settings-search-excluded-search-terms').scrollIntoView();
        cy.get('.sw-settings-search-excluded-search-terms').contains('.sw-card__title', 'Excluded search terms');

        cy.get('.sw-settings-search-excluded-search-terms')
            .contains('.sw-settings-search-excluded-search-terms__insert-button', 'Exclude search term')
            .click();
        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 input[name=sw-field--currentValue]`)
            .clearTypeAndCheck('Example');
        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 .sw-data-grid__inline-edit-save`)
            .click();

        cy.wait('@updateProductSearchConfig').its('response.statusCode').should('within', 200, 204);
        cy.awaitAndCheckNotification('Excluded search term created.');

        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0`)
            .contains('.sw-data-grid__cell-value', 'Example');
    });

    it('@settings: delete search', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product-search-config`,
            method: 'POST',
        }).as('getProductSearchConfig');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product-search-config-field`,
            method: 'POST',
        }).as('getProductSearchConfigField');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-search-config/*`,
            method: 'PATCH',
        }).as('updateProductSearchConfig');

        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'viewer',
            },
            {
                key: 'product_search_config',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-tabs').should('be.visible');
        cy.get('.sw-tabs').contains('.sw-tabs-item:first-child', 'General').click();

        cy.wait('@getProductSearchConfig').its('response.statusCode').should('equal', 200);
        cy.wait('@getProductSearchConfigField').its('response.statusCode').should('equal', 200);

        cy.get('.sw-settings-search-excluded-search-terms').scrollIntoView();
        cy.get('.sw-settings-search-excluded-search-terms').contains('.sw-card__title', 'Excluded search terms');

        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 .sw-context-button__button`).click();
        cy.get('.sw-context-menu-item.sw-context-menu-item--danger').should('be.visible');
        cy.get('.sw-context-menu-item.sw-context-menu-item--danger').click();

        cy.wait('@updateProductSearchConfig').its('response.statusCode').should('within', 200, 204);
        cy.awaitAndCheckNotification('Excluded search term deleted.');
    });
});
