// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Currency: Test acl privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('currency');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: can view currency', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'currencies',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/currency/index`);
        });

        // click on first element in grid
        cy.get(`${page.elements.dataGridRow}--2`)
            .contains('Euro')
            .click();

        // check if values are visible
        cy.get('#sw-field--currency-name').should('have.value', 'Euro');
        cy.get('#sw-field--currency-isoCode').should('have.value', 'EUR');
        cy.get('#sw-field--currency-shortName').should('have.value', 'EUR');
        cy.get('#sw-field--currency-factor').should('have.value', '1');
    });

    it('@settings: can edit currency', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'currencies',
                role: 'viewer'
            },
            {
                key: 'currencies',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/currency/index`);
        });

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/currency/*`,
            method: 'patch'
        }).as('saveCurrency');

        // click on first element in grid
        cy.get(`${page.elements.dataGridRow}--2`)
            .contains('Euro')
            .click();

        // edit name
        cy.get('#sw-field--currency-name').clear().type('Kreuzer');

        // save currency
        cy.get(page.elements.currencySaveAction).click();

        // Verify creation
        cy.wait('@saveCurrency').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Kreuzer');
        cy.get('.sw-currency-list__content').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Kreuzer');
    });

    it('@settings: can create currency', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'currencies',
                role: 'viewer'
            },
            {
                key: 'currencies',
                role: 'editor'
            },
            {
                key: 'currencies',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/currency/index`);
        });

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/currency`,
            method: 'post'
        }).as('saveCurrency');

        // Create currency
        cy.get('a[href="#/sw/settings/currency/create"]').click();

        cy.get('input[name=sw-field--currency-name]').typeAndCheck('0000 Dukaten');
        cy.get('input[name=sw-field--currency-isoCode]').type('D');
        cy.get('input[name=sw-field--currency-shortName]').type('D');
        cy.get('input[name=sw-field--currency-symbol]').type('D¥');
        cy.get('input[name=sw-field--currency-factor]').type('1.0076');
        cy.get(page.elements.currencySaveAction).click();

        // Verify creation
        cy.wait('@saveCurrency').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-currency-list__content').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.currencyColumnName}`).contains('Dukaten');
    });

    it('@settings: can delete currency', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'currencies',
                role: 'viewer'
            },
            {
                key: 'currencies',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/currency/index`);
        });

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/currency/*`,
            method: 'delete'
        }).as('deleteCurrency');

        // filter currency via search bar
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('ZZ Yen');

        // Delete currency
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete the currency "ZZ Yen"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        // Verify deletion
        cy.wait('@deleteCurrency').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.modal).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.currencyColumnName}`).should('not.exist');
    });
});
