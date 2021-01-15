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
                cy.onlyOnFeature('FEATURE_NEXT_10552');
            });
    });

    it('@settings: show modal when click onto the example link', () => {
        cy.get('.sw-settings-search__searchable-content-show-example-link').click();
        cy.get('.sw-modal.sw-settings-search-example-modal').should('be.visible');
    });

    it('@settings: reset config to default on general tab', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/product-search-config-field/*',
            method: 'patch'
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

        cy.wait('@updateSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.awaitAndCheckNotification('The configuration has been saved.');

        cy.get('.sw-settings-search__searchable-content-reset-button').click();
        cy.wait('@updateSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.awaitAndCheckNotification('The configuration has been saved.');

        // Check ranking points already reset
        cy.get('.sw-settings-search__searchable-content-general .sw-data-grid__row--0 .sw-data-grid__cell-value')
            .invoke('text').then((text) => {
                expect(text.trim()).equal('0');
            });
    });

    it('@settings: reset config to default on custom fields tab', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/search/product-search-config-field',
            method: 'post'
        }).as('getData');

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

        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // change to customfield tab
        cy.get('.sw-settings-search__searchable-content-tab-title').last().click();
        cy.get('.sw-settings-search__view-general .sw-card:nth-child(2)').scrollIntoView();
        cy.wait('@getCustomField').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Create a new item first and then reset to default.
        cy.get('.sw-settings-search__searchable-content-add-button').click();

        cy.get(`.sw-settings-search__searchable-content-customfields ${page.elements.dataGridRow}--0`).dblclick();

        cy.get('.sw-settings-search-custom-field-select')
            .typeSingleSelectAndCheck('custom_field_set_property', '.sw-settings-search-custom-field-select');

        cy.get(`${page.elements.dataGridRow}--0 #sw-field--item-ranking`).clear().type('2000');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--searchable input`).click();
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--tokenize input`).click();
        cy.get(`${page.elements.dataGridRowInlineEdit}`).click();
        cy.wait('@createSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.awaitAndCheckNotification('The configuration has been saved.');

        cy.get('.sw-settings-search__searchable-content-reset-button').click();

        cy.wait('@updateSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.awaitAndCheckNotification('The configuration has been saved.');

        // Check ranking points already updated
        cy.get('.sw-settings-search__searchable-content-customfields .sw-data-grid__row--0 .sw-data-grid__cell-value')
            .invoke('text').then((text) => {
                expect(text.trim()).equal('0');
            });
    });

    it('@settings: Delete config field', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/product-search-config-field/*',
            method: 'delete'
        }).as('deleteSearchConfig');

        cy.get('.sw-settings-search__view-general .sw-card:nth-child(2)').scrollIntoView();

        // Get field Name on first row
        let fieldName = '';
        cy.get('.sw-settings-search__searchable-content-general ' +
            '.sw-data-grid__row--0 .sw-data-grid__cell-content:first')
            .invoke('text')
            .then((text) => {
                fieldName = text;
            });
        cy.clickContextMenuItem(
            '.sw-settings-search__searchable-content-list-remove',
            page.elements.contextMenuButton,
            `.sw-settings-search__searchable-content-general ${page.elements.dataGridRow}--0`
        );

        cy.wait('@deleteSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.awaitAndCheckNotification('The configuration has been saved.');

        // Make sure that the field was deleted
        cy.get('.sw-settings-search__searchable-content-general ' +
            '.sw-data-grid__row--0 .sw-data-grid__cell-content:first')
            .invoke('text')
            .then((text) => {
                expect(text).to.not.equal(fieldName);
            });
    });

    it('@settings: Create a new config field', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/product-search-config-field/*',
            method: 'delete'
        }).as('deleteSearchConfig');

        cy.route({
            url: '/api/product-search-config-field',
            method: 'post'
        }).as('createSearchConfig');

        cy.get('.sw-settings-search__view-general .sw-card:nth-child(2)').scrollIntoView();

        cy.clickContextMenuItem(
            '.sw-settings-search__searchable-content-list-remove',
            page.elements.contextMenuButton,
            `.sw-settings-search__searchable-content-general ${page.elements.dataGridRow}--0`
        );

        cy.wait('@deleteSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-settings-search__searchable-content-add-button').click();
        cy.get('.sw-settings-search__view-general .sw-card:nth-child(2)').scrollIntoView();
        //
        cy.get(`.sw-settings-search__searchable-content-general ${page.elements.dataGridRow}--0`).dblclick();
        cy.get('.sw-settings-search-field-select')
            .typeSingleSelectAndCheck('Categories custom fields', '.sw-settings-search-field-select');
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0 #sw-field--item-ranking`).clear().type('2000');
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--searchable input`).click();
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--tokenize input`).click();
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRowInlineEdit}`).click();

        cy.wait('@createSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Check field already created
        cy.get('.sw-settings-search__searchable-content-general .sw-data-grid__row--0 .sw-data-grid__cell-content:first')
            .invoke('text').then((text) => {
                expect(text.trim()).equal('Categories custom fields');
            });
    });

    it('@settings: Update config field', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api//product-search-config-field/*',
            method: 'patch'
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

        cy.wait('@updateSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Check ranking points already updated
        cy.get('.sw-settings-search__searchable-content-general ' +
            '.sw-data-grid__row--0 .sw-data-grid__cell-value').invoke('text').then((text) => {
            expect(text.trim()).equal('9999');
        });
    });

    it('@settings: Reset ranking for config field', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/product-search-config-field/*',
            method: 'patch'
        }).as('updateSearchConfig');

        cy.route({
            url: '/api/search/product-search-config-field',
            method: 'post'
        }).as('getData');

        // update the value ranking which is not same default value
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0`).dblclick();
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0 #sw-field--item-ranking`).clear().type('8888');
        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRowInlineEdit}`).click();

        cy.get('.sw-settings-search__view-general .sw-card:nth-child(2)').scrollIntoView();

        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.awaitAndCheckNotification('The configuration has been saved.');
        // cy.wait(3000);
        cy.clickContextMenuItem(
            '.sw-settings-search__searchable-content-list-reset',
            page.elements.contextMenuButton,
            `.sw-settings-search__searchable-content-general ${page.elements.dataGridRow}--0`
        );

        cy.wait('@updateSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Check ranking points already updated
        cy.get('.sw-settings-search__searchable-content-general ' +
            '.sw-data-grid__row--0 .sw-data-grid__cell-value').invoke('text').then((text) => {
            expect(text.trim()).equal('0');
        });
    });

    it('@settings: Can not create a config field which was existed', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();

        cy.route({
            url: '/api/product-search-config-field',
            method: 'post'
        }).as('createSearchConfig');

        cy.route({
            url: '/api/search/product-search-config-field',
            method: 'post'
        }).as('getData');

        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-settings-search__view-general .sw-card:nth-child(2)').scrollIntoView();

        cy.get('.sw-settings-search__searchable-content-add-button').click();

        cy.get('.sw-settings-search__searchable-content-general ' +
            `${page.elements.dataGridRow}--0`).dblclick();
        cy.get(`${page.elements.dataGridRowInlineEdit}`).click();

        cy.wait('@createSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 400);
        });
    });
});
