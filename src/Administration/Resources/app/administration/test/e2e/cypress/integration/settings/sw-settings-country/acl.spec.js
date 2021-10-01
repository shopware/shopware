// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Country: Test acl privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('country');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: can view a list of countries', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'country',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        });

        // assert that there is an available list of countries
        cy.get(`${page.elements.countryListContent}`).should('be.visible');
    });

    it('@settings: can edit a country', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'country',
                role: 'viewer'
            },
            {
                key: 'country',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        });

        // prepare api to update a country
        cy.intercept({
            url: `${Cypress.env('apiPath')}/country/*`,
            method: 'PATCH'
        }).as('saveCountry');

        // click on first element in grid
        cy.get(`
            ${page.elements.dataGridRow}--0
            ${page.elements.countryColumnName}
        `).click();

        // Edit name, position, iso, iso3
        cy.get('#sw-field--country-name')
            .clear()
            .type('000');
        cy.get('#sw-field--country-position')
            .clear()
            .type('101');
        cy.get('#sw-field--country-iso')
            .clear()
            .type('101');
        cy.get('#sw-field--country-iso3')
            .clear()
            .type('101');

        // do saving action
        cy.get(page.elements.countrySaveAction).click();

        // call api to update the country
        cy.wait('@saveCountry').its('response.statusCode').should('equal', 204);

        // assert that country is updated successfully
        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('000');
        cy.get(`${page.elements.countryListContent}`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`)
            .should('be.visible')
            .contains('000');
    });

    it('@settings: can create a country', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'country',
                role: 'viewer'
            },
            {
                key: 'country',
                role: 'editor'
            },
            {
                key: 'country',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        });

        // prepare api to create a new country
        cy.intercept({
            url: `${Cypress.env('apiPath')}/country`,
            method: 'POST'
        }).as('saveCountry');

        // click on "Add country" button
        cy.get('a[href="#/sw/settings/country/create"]').click();

        // Enter name, position, iso, iso3
        cy.get('#sw-field--country-name')
            .clear()
            .type('000');
        cy.get('#sw-field--country-position')
            .clear()
            .type('101');
        cy.get('#sw-field--country-iso')
            .clear()
            .type('101');
        cy.get('#sw-field--country-iso3')
            .clear()
            .type('101');

        // do saving action
        cy.get(page.elements.countrySaveAction).click();

        // call api to create a new country
        cy.wait('@saveCountry').its('response.statusCode').should('equal', 204);

        // assert that country is created successfully
        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.countryListContent}`).should('be.visible');
        cy.get(`
            ${page.elements.dataGridRow}--0
            ${page.elements.countryColumnName}
        `).contains('000');
    });

    it('@settings: can delete a country', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'country',
                role: 'viewer'
            },
            {
                key: 'country',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        });

        // prepare api to delete a country
        cy.intercept({
            url: `${Cypress.env('apiPath')}/country/*`,
            method: 'delete'
        }).as('deleteCountry');

        // find a country with the name is "Zimbabwe"
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Zimbabwe');

        // choose delete action
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // assert that confirmation modal appears
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body').contains('Are you sure you want to delete the country "Zimbabwe"?');

        // do deleting action
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        // call api to delete the country
        cy.wait('@deleteCountry').its('response.statusCode').should('equal', 204);

        // assert that modal is off, country is deleted
        cy.get(page.elements.modal).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).should('not.exist');
    });

    it('@settings: can view a list of states', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'country',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        });

        // find a country with the name is "Germany"
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Germany');

        // choose "Germany"
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).click();

        // choose "state tab"
        cy.get('.sw-settings-country__state-tab').click();
        cy.get(`${page.elements.countryStateListContent}`).should('be.visible');

        // assert that there is an available list of Germany's states
        cy.get(`${page.elements.countryStateListContent}`).should('be.visible');
    });

    it('@settings: can edit a state', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'country',
                role: 'viewer'
            },
            {
                key: 'country',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        });

        // prepare api to update a state
        cy.intercept({
            url: `${Cypress.env('apiPath')}/country/*/states/*`,
            method: 'PATCH'
        }).as('saveCountryState');

        // find a country with the name is "Germany"
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Germany');

        // choose "Germany"
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).click();

        // choose "state tab"
        cy.get('.sw-settings-country__state-tab').click();

        cy.get('.sw-data-grid.sw-settings-country-state-list__content')
            .should('be.visible');

        // click on the first element in grid
        cy.get(`
            ${page.elements.dataGridRow}--0
            .sw-context-button__button
        `).click();

        cy.get('.sw-context-menu__content').should('be.visible');
        cy.get('.sw-context-menu-item.sw-settings-country-state__edit-country-state-action').click();

        // assert that modal appears
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__title').contains('Edit state/region');

        // edit name, shortCode, position
        cy.get('#sw-field--countryState-name')
            .clear()
            .type('000');
        cy.get('#sw-field--countryState-shortCode')
            .clear()
            .type('101');
        cy.get('#sw-field--countryState-position')
            .clear()
            .type('101');

        // do saving action
        cy.get(page.elements.countryStateSaveAction).click();

        // call api to update state
        cy.wait('@saveCountryState').its('response.statusCode').should('equal', 204);

        // assert that state is updated successfully
        cy.get(`${page.elements.countryStateListContent}`).should('be.visible');
        cy.get(`
            ${page.elements.countryStateListContent}
            ${page.elements.dataGridRow}--0
            ${page.elements.countryStateColumnName}`
        ).should('be.visible').contains('000');

        // assert that state is updated successfully
        cy.get('.sw-settings-country-state-list__content').should('be.visible');
        cy.get(`
            .sw-settings-country-state-list__content
            ${page.elements.dataGridRow}--0
            ${page.elements.countryStateColumnName}`
        ).should('be.visible').contains('000');
    });

    it('@settings: can create a state', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'country',
                role: 'viewer'
            },
            {
                key: 'country',
                role: 'creator'
            },
            {
                key: 'country',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        });

        // prepare api to create a state
        cy.intercept({
            url: `${Cypress.env('apiPath')}/country/*/states`,
            method: 'POST'
        }).as('saveCountryState');

        // find a country with the name is "Germany"
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Germany');

        // choose "Germany"
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).click();

        // choose "state tab"
        cy.get('.sw-settings-country__state-tab').click();
        // click on "add state" button
        cy.get('.sw-settings-country-state__add-country-state-button').click();

        // assert that modal appears
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__title').contains('New state/region');

        // enter name, shortCode, position
        cy.get('#sw-field--countryState-name').typeAndCheck('000');
        cy.get('#sw-field--countryState-shortCode').typeAndCheck('101');
        cy.get('#sw-field--countryState-position').typeAndCheck('101');

        // do saving action
        cy.get(page.elements.countryStateSaveAction).click();

        // call api to create state
        cy.wait('@saveCountryState').its('response.statusCode').should('equal', 204);

        // assert that state is created successfully
        cy.get(`${page.elements.countryStateListContent}`).should('be.visible');
        cy.get(`
            ${page.elements.countryStateListContent}
            ${page.elements.countryStateColumnName}
        `).should('be.visible').contains('000');

        // assert that state is created successfully
        cy.get(`.sw-settings-country-state-list__content ${page.elements.countryStateColumnName}`)
            .should('be.visible').contains('000');
    });

    it('@settings: can not create a state', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'country',
                role: 'viewer'
            },
            {
                key: 'country',
                role: 'creator'
            },
            {
                key: 'country',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        });

        // prepare api to create a state
        cy.intercept({
            url: `${Cypress.env('apiPath')}/country/*/states`,
            method: 'POST'
        }).as('saveCountryState');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/language`,
            method: 'POST'
        }).as('searchLanguage');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/country`,
            method: 'POST'
        }).as('searchCountry');

        cy.get('.sw-language-switch__select .sw-entity-single-select__selection-text').contains('English');
        cy.get('.smart-bar__content .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        // poor assertion to check if there is more than 1 language
        cy.get('.sw-select-result-list__item-list .sw-select-result')
            .should('have.length.greaterThan', 1);
        cy.get('.sw-select-result-list__item-list .sw-select-result').contains('Deutsch').click();

        cy.wait('@searchLanguage').its('response.statusCode').should('equals', 200);

        // find a country with the name is "Germany"
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Vietnam');
        cy.wait('@searchCountry').its('response.statusCode').should('equals', 200);

        // choose "Germany"
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).click();

        // choose "state tab"
        cy.get('.sw-settings-country__state-tab').click();
        // click on "add state" button
        cy.get('.sw-settings-country-state__add-country-state-button').click();

        // assert that modal appears
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__title').contains('New state/region');

        // enter name, shortCode, position
        cy.get('#sw-field--countryState-name').typeAndCheck('000');
        cy.get('#sw-field--countryState-shortCode').typeAndCheck('101');
        cy.get('#sw-field--countryState-position').typeAndCheck('101');

        // do saving action
        cy.get(page.elements.countryStateSaveAction).click();

        // call api to create state
        cy.wait('@saveCountryState').its('response.statusCode').should('equals', 400);
        cy.get('.sw-alert__body').should('be.visible');
        cy.get('.sw-alert__body .sw-alert__message')
            .should('be.visible')
            .contains('Cannot create a region with the currently selected language. Please create a region using the default system language first.');
    });

    it('@settings: can delete multiple countries', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'country',
                role: 'viewer'
            },
            {
                key: 'country',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        });


        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('deleteCountry');

        // click on first checkbox in grid
        cy.get(`${page.elements.dataGridRow}--0 .sw-field--checkbox`).click();
        cy.get(`${page.elements.dataGridRow}--1 .sw-field--checkbox`).click();
        cy.get('.sw-data-grid__bulk .bulk-link').click();
        cy.get('.sw-modal__dialog .sw-button--danger').click();

        // assert that there is an available list of countries
        cy.get(`${page.elements.countryListContent}`).should('be.visible');

        cy.wait('@deleteCountry')
            .its('response.statusCode').should('equal', 200);
    });
});
