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
        ]);

        // go to countries module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-country').click();

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
        ]);

        // prepare api to update a country
        cy.server();
        cy.route({
            url: '/api/v*/country/*',
            method: 'patch'
        }).as('saveCountry');

        // go to countries module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-country').click();

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
        cy.wait('@saveCountry').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

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
        ]);

        // prepare api to create a new country
        cy.server();
        cy.route({
            url: '/api/v*/country',
            method: 'post'
        }).as('saveCountry');

        // go to countries module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-country').click();

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
        cy.wait('@saveCountry').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

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
        ]);

        // prepare api to delete a country
        cy.server();
        cy.route({
            url: '/api/v*/country/*',
            method: 'delete'
        }).as('deleteCountry');

        // go to countries module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-country').click();

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
        cy.wait('@deleteCountry').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

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
        ]);

        // go to countries module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-country').click();

        // find a country with the name is "Germany"
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Germany');

        // choose "Germany"
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).click();

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
        ]);

        // prepare api to update a state
        cy.server();
        cy.route({
            url: '/api/v*/country/*/states/*',
            method: 'patch'
        }).as('saveCountryState');

        // go to countries module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-country').click();

        // find a country with the name is "Germany"
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Germany');

        // choose "Germany"
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).click();

        // click on the first element in grid
        cy.get(`
            ${page.elements.countryStateListContent}
            ${page.elements.dataGridRow}--0
            ${page.elements.countryStateColumnName}
        `).click();

        // assert that modal appears
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__title').contains('Edit state/province');

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
        cy.wait('@saveCountryState').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // assert that state is updated successfully
        cy.get(`${page.elements.countryStateListContent}`).should('be.visible');
        cy.get(`
            ${page.elements.countryStateListContent}
            ${page.elements.dataGridRow}--0
            ${page.elements.countryStateColumnName}
        `).should('be.visible').contains('000');
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
        ]);

        // prepare api to create a state
        cy.server();
        cy.route({
            url: '/api/v*/country/*/states',
            method: 'post'
        }).as('saveCountryState');

        // go to countries module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-country').click();

        // find a country with the name is "Germany"
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Germany');

        // choose "Germany"
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).click();

        // click on "add state" button
        cy.get(`${page.elements.countryStateAddAction}`).click();

        // assert that modal appears
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__title').contains('New state/province');

        // enter name, shortCode, position
        cy.get('#sw-field--countryState-name').typeAndCheck('000');
        cy.get('#sw-field--countryState-shortCode').typeAndCheck('101');
        cy.get('#sw-field--countryState-position').typeAndCheck('101');

        // do saving action
        cy.get(page.elements.countryStateSaveAction).click();

        // call api to create state
        cy.wait('@saveCountryState').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // assert that state is created successfully
        cy.get(`${page.elements.countryStateListContent}`).should('be.visible');
        cy.get(`
            ${page.elements.countryStateListContent}
            ${page.elements.countryStateColumnName}
        `).should('be.visible').contains('000');
    });
});
