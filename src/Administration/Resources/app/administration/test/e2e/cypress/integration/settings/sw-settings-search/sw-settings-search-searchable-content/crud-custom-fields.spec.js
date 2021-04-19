// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Product Search: Test crud operations of custom field', () => {
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

    it('@settings: Create a config field', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/product-search-config-field',
            method: 'post'
        }).as('createSearchConfig');

        cy.route({
            url: '/api/search/custom-field',
            method: 'post'
        }).as('getCustomField');

        cy.route({
            url: '/api/search/product-search-config-field',
            method: 'post'
        }).as('getData');

        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // change to custom field tab
        cy.get('.sw-settings-search__searchable-content-tab-title').last().click();
        cy.get('.sw-settings-search__view-general .sw-card:nth-child(1)').scrollIntoView();

        cy.wait('@getCustomField').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-settings-search__searchable-content-customfields .sw-empty-state__title')
            .contains('No searchable content added yet.');
        cy.get('.sw-settings-search__searchable-content-add-button').should('exist');
        cy.get('.sw-settings-search__searchable-content-add-button').click();

        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0`).dblclick();

        cy.get('.sw-settings-search-custom-field-select')
            .typeSingleSelectAndCheck('custom_field_set_property', '.sw-settings-search-custom-field-select');
        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0 #sw-field--item-ranking`).clear().type('9999');
        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--searchable input`).click();
        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--tokenize input`).click();
        cy.get(`${page.elements.dataGridRowInlineEdit}`).click();

        cy.wait('@createSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Check field already created
        cy.get('.sw-settings-search__searchable-content-customfields ' +
            '.sw-data-grid__row--0 .sw-data-grid__cell-content:first').invoke('text').then((text) => {
            expect(text.trim()).equal('My custom field - custom_field_set_property');
        });
    });

    it('@settings: Update config field', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/product-search-config-field',
            method: 'post'
        }).as('createSearchConfig');
        cy.route({
            url: '/api/product-search-config-field/*',
            method: 'patch'
        }).as('updateSearchConfig');

        cy.route({
            url: '/api/search/custom-field',
            method: 'post'
        }).as('getCustomField');

        cy.route({
            url: '/api/search/product-search-config-field',
            method: 'post'
        }).as('getData');

        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // change to custom field tab
        cy.get('.sw-settings-search__searchable-content-tab-title').last().click();
        cy.get('.sw-settings-search__view-general .sw-card:nth-child(2)').scrollIntoView();

        cy.wait('@getCustomField').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Create a new item first and then update it.
        cy.get('.sw-settings-search__searchable-content-add-button').click();

        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0`).dblclick();

        cy.get('.sw-settings-search-custom-field-select')
            .typeSingleSelectAndCheck('custom_field_set_property', '.sw-settings-search-custom-field-select');

        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0 #sw-field--item-ranking`).clear().type('2000');
        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--searchable input`).click();
        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--tokenize input`).click();
        cy.get(`${page.elements.dataGridRowInlineEdit}`).click();
        cy.wait('@createSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0`).dblclick();
        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0 #sw-field--item-ranking`).clear().type('1000');

        cy.get(`${page.elements.dataGridRowInlineEdit}`).click();

        cy.wait('@updateSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Check ranking points already updated
        cy.get('.sw-settings-search__searchable-content-customfields ' +
            '.sw-data-grid__row--0 .sw-data-grid__cell-value').invoke('text').then((text) => {
            expect(text.trim()).equal('1000');
        });
    });

    it('@settings: Delete config field', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/product-search-config-field',
            method: 'post'
        }).as('createSearchConfig');

        cy.route({
            url: '/api/product-search-config-field/*',
            method: 'delete'
        }).as('deleteSearchConfig');

        cy.route({
            url: '/api/search/custom-field',
            method: 'post'
        }).as('getCustomField');

        cy.route({
            url: '/api/search/product-search-config-field',
            method: 'post'
        }).as('getData');

        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // change to customfield tab
        cy.get('.sw-settings-search__searchable-content-tab-title').last().click();
        cy.get('.sw-settings-search__view-general .sw-card:nth-child(2)').scrollIntoView();

        cy.wait('@getCustomField').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Create a new item first and then delete it.
        cy.get('.sw-settings-search__searchable-content-add-button').click();

        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0`).dblclick();

        cy.get('.sw-settings-search-custom-field-select')
            .typeSingleSelectAndCheck('custom_field_set_property', '.sw-settings-search-custom-field-select');

        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0 #sw-field--item-ranking`).clear().type('2000');
        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--searchable input`).click();
        cy.get('.sw-settings-search__searchable-content-customfields ' +
            `${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--tokenize input`).click();
        cy.get(`${page.elements.dataGridRowInlineEdit}`).click();

        cy.wait('@createSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-settings-search__view-general .sw-card:nth-child(2)').scrollIntoView();
        cy.clickContextMenuItem(
            '.sw-settings-search__searchable-content-list-remove',
            page.elements.contextMenuButton,
            `.sw-settings-search__searchable-content-customfields ${page.elements.dataGridRow}--0`
        );

        cy.wait('@deleteSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-empty-state').should('exist');
    });
});
