// / <reference types="Cypress" />
import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Search: Test ACL privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('custom-field-set');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    // TODO skipped due to flakiness, see NEXT-15696
    it.skip('@settings: access the search but without rights', () => {
        cy.loginAsUserWithPermissions([]);

        cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
        cy.location('hash').should('eq', '#/sw/privilege/error/index');
    });

    // TODO skipped due to flakiness, see NEXT-15696
    it.skip('@settings: can view the general tab and live search tab content', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
        });

        // assert that there is an available settings for search module
        cy.get('.sw-settings-search__view-general').should('be.visible');
        cy.get('.sw-settings-search__view-general').scrollIntoView();
        cy.get('.sw-settings-search__searchable-content-general').should('be.visible');

        cy.get('.sw-settings-search-excluded-search-terms').scrollIntoView();
        cy.get('.sw-settings-search-excluded-search-terms').should('be.visible');

        // live search tab should be accessible
        cy.get('.sw-settings-search__general-tab').scrollIntoView();
        cy.get('.sw-settings-search__live-search-tab').should('be.visible').click();
        cy.get('.sw-settings-search-live-search').should('be.visible');

        // click save btn and see if anything happens
        cy.get('.sw-settings-search__button-save').should('have.class', 'sw-button--disabled');
        cy.get('.sw-settings-search__button-save').should('be.disabled');
    });

    // TODO skipped due to flakiness, see NEXT-15696
    // Search behaviour section
    it.skip('@settings: can edit search behaviour settings if having editor/creator privilege', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'viewer'
            },
            {
                key: 'product_search_config',
                role: 'editor'
            },
            {
                key: 'product_search_config',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
        });

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/*`,
            method: 'post'
        }).as('editSearchConfigs');

        // search behaviour should not be allowed to edit
        cy.get('.sw-settings-search__search-behaviour-condition').find('label').eq(0).click();
        cy.get('.sw-settings-search__search-behaviour-condition').find('input').eq(0).should('be.checked');

        cy.get('.sw-settings-search__search-behaviour-condition').find('label').eq(1).click();
        cy.get('.sw-settings-search__search-behaviour-condition').find('input').eq(1).should('be.checked');

        cy.get('.sw-settings-search__search-behaviour-term-length input').clear().typeAndCheck(4);
        cy.get('.sw-settings-search__button-save').click();

        // Verify update
        cy.wait('@editSearchConfigs').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.awaitAndCheckNotification('Configuration saved.');
    });

    // TODO skipped due to flakiness, see NEXT-15696
    // Searchable content section - General tab
    it.skip('@settings: should able to update config field', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'viewer'
            },
            {
                key: 'product_search_config',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
        });

        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/product-search-config-field/*',
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

    // TODO skipped due to flakiness, see NEXT-15696
    it.skip('@settings: should able to reset config to default on general tab', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'viewer'
            },
            {
                key: 'product_search_config',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
        });

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
        cy.awaitAndCheckNotification('Configuration saved.');

        cy.get('.sw-settings-search__searchable-content-reset-button').click();
        cy.wait('@updateSearchConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.awaitAndCheckNotification('Configuration saved.');

        // Check ranking points already reset
        cy.get('.sw-settings-search__searchable-content-general .sw-data-grid__row--0 .sw-data-grid__cell-value')
            .invoke('text').then((text) => {
                expect(text.trim()).equal('0');
            });
    });

    // TODO skipped due to flakiness, see NEXT-15696
    // Searchable content section -> Custom field tab
    it.skip('@settings: should able to create a custom config field', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'viewer'
            },
            {
                key: 'product_search_config',
                role: 'editor'
            },
            {
                key: 'product_search_config',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
        });

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

    // TODO skipped due to flakiness, see NEXT-15696
    it.skip('@settings: should able to update config field', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'viewer'
            },
            {
                key: 'product_search_config',
                role: 'editor'
            },
            {
                key: 'product_search_config',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
        });

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

        // change to customfield tab
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

    // TODO skipped due to flakiness, see NEXT-15696
    it.skip('@settings: should able to delete config field', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'viewer'
            },
            {
                key: 'product_search_config',
                role: 'creator'
            },
            {
                key: 'product_search_config',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
        });

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

    // TODO skipped due to flakiness, see NEXT-15696
    // Excluded search terms section
    it.skip('@settings: can create the excluded search terms having creator privilege', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
        });

        const page = new SettingsPageObject();
        cy.server();
        cy.route({
            url: '/api/product-search-config/*',
            method: 'patch'
        }).as('saveData');

        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-settings-search-excluded-search-terms__insert-button').click();
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0`).dblclick();
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0 input[name=sw-field--currentValue]`).type('example');

        // Submit add new excluded term
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0 .sw-button.sw-data-grid__inline-edit-save`).click();

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.awaitAndCheckNotification('Excluded search term created.');
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0 .sw-data-grid__cell-value`).contains('example');
    });

    // TODO skipped due to flakiness, see NEXT-15696
    it.skip('@settings: can update the excluded search terms having editor/creator privilege', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'editor'
            },
            {
                key: 'product_search_config',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
        });

        const page = new SettingsPageObject();
        cy.server();
        cy.route({
            url: '/api/product-search-config/*',
            method: 'patch'
        }).as('saveData');

        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0`).dblclick();
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0 input[name=sw-field--currentValue]`).clear().type('update');

        // Submit add new excluded term
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0 .sw-button.sw-data-grid__inline-edit-save`).click();

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.awaitAndCheckNotification('Excluded search term updated.');
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0 .sw-data-grid__cell-value`).contains('update');
    });

    // TODO skipped due to flakiness, see NEXT-15696
    it.skip('@settings: should able to a delete a excluded terms if having deleter privilege', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
        });

        const page = new SettingsPageObject();
        cy.server();

        cy.route({
            url: '/api/product-search-config/*',
            method: 'patch'
        }).as('saveData');

        // Single delete excluded term
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0 .sw-context-button__button`).click();
        cy.get('.sw-context-menu-item.sw-context-menu-item--danger').click();

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.awaitAndCheckNotification('Excluded search term deleted.');

        // Bulk delete excluded term
        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-data-grid__row .sw-data-grid__cell.sw-data-grid__cell--header.sw-data-grid__cell--selection input')
            .check();
        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-data-grid__bulk-selected.sw-data-grid__bulk-selected-count').contains(10);
        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-data-grid__bulk .sw-data-grid__bulk-selected.bulk-link button').should('be.visible');
        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-data-grid__bulk .sw-data-grid__bulk-selected.bulk-link button').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.awaitAndCheckNotification('Excluded search term deleted.');
    });

    // TODO skipped due to flakiness, see NEXT-15696
    // Rebuild search index section
    it.skip('@settings: can rebuild the search index if having editor/creator privilege', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product_search_config',
                role: 'editor'
            },
            {
                key: 'product_search_config',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/search/index`);
        });

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/indexing/product.indexer`,
            method: 'post'
        }).as('buildSearchIndex');

        cy.get('.sw-settings-search__general-tab').scrollIntoView();
        cy.get('.sw-settings-search__live-search-tab').should('be.visible').click();
        cy.get('.sw-settings-search-live-search').should('be.visible');

        cy.get('.sw-settings-search__search-index-rebuild-button').scrollIntoView().click();
        cy.awaitAndCheckNotification('Building product indexes.');

        // Verify build search index calling
        cy.wait('@buildSearchIndex').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.awaitAndCheckNotification('Product indexes built.');
    });
});
