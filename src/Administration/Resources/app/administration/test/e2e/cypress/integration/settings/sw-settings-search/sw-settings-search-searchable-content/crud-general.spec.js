// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Product Search: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('custom-field-set');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/search/index`);
            });
    });

    it('@settings: show modal when click onto the example link', () => {
        cy.get('.sw-settings-search__searchable-content-show-example-link').click();
        cy.get('.sw-modal.sw-settings-search-example-modal').should('be.visible');
    });

    it('@settings: reset config to default on general tab', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-search-config-field/*`,
            method: 'PATCH'
        }).as('updateSearchConfig');

        cy.get('.sw-settings-search__view-general .sw-card:nth-child(2)').scrollIntoView();

        cy.get(`.sw-settings-search__searchable-content-general ${page.elements.dataGridRow}--0`).dblclick();

        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0 #sw-field--item-ranking`).clear().type('9999');
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--searchable input`).click();
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--tokenize input`).click();
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRowInlineEdit}`).click();

        cy.wait('@updateSearchConfig')
            .its('response.statusCode').should('equal', 204);
        cy.awaitAndCheckNotification('Configuration saved.');

        cy.get('.sw-settings-search__searchable-content-reset-button').click();
        cy.wait('@updateSearchConfig')
            .its('response.statusCode').should('equal', 204);
        cy.awaitAndCheckNotification('Configuration saved.');

        // Check ranking points already reset
        cy.get('.sw-settings-search__searchable-content-general .sw-data-grid__row--0 .sw-data-grid__cell-value')
            .invoke('text').then((text) => {
                expect(text.trim()).equal('0');
            });
    });

    it('@settings: Update config field', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}//product-search-config-field/*`,
            method: 'PATCH'
        }).as('updateSearchConfig');

        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0`).dblclick();

        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0 #sw-field--item-ranking`).clear().type('9999');
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--searchable input`).click();
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--tokenize input`).click();
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRowInlineEdit}`).click();

        cy.wait('@updateSearchConfig')
            .its('response.statusCode').should('equal', 204);

        // Check ranking points already updated
        cy.get('.sw-settings-search__searchable-content-general ' +
            '.sw-data-grid__row--0 .sw-data-grid__cell-value').invoke('text').then((text) => {
            expect(text.trim()).equal('9999');
        });
    });

    it('@settings: Reset ranking for config field', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-search-config-field/*`,
            method: 'PATCH'
        }).as('updateSearchConfig');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product-search-config-field`,
            method: 'POST'
        }).as('getData');

        // update the value ranking which is not same default value
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0`).dblclick();
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0 #sw-field--item-ranking`).clear().type('8888');
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRowInlineEdit}`).click();

        cy.get('.sw-settings-search__view-general .sw-card:nth-child(2)').scrollIntoView();

        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.awaitAndCheckNotification('Configuration saved.');
        // cy.wait(3000);
        cy.clickContextMenuItem(
            '.sw-settings-search__searchable-content-list-reset',
            page.elements.contextMenuButton,
            `.sw-settings-search__searchable-content-general ${page.elements.dataGridRow}--0`
        );

        cy.wait('@updateSearchConfig')
            .its('response.statusCode').should('equal', 204);

        // Check ranking points already updated
        cy.get('.sw-settings-search__searchable-content-general ' +
            '.sw-data-grid__row--0 .sw-data-grid__cell-value').invoke('text').then((text) => {
            expect(text.trim()).equal('0');
        });
    });
});
