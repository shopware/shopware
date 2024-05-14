/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Currency: Test acl privileges', () => {
    beforeEach(() => {
        cy.createDefaultFixture('currency')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: can view currency', { tags: ['pa-inventory', 'VUE3_SKIP'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'currencies',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/currency/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-settings-currency-list-grid').should('be.visible');
        cy.contains('.sw-data-grid__cell-content', 'US-Dollar').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('Euro')
            .click();

        // check if values are visible
        cy.get('#sw-field--currency-name').should('have.value', 'Euro');
        cy.get('#sw-field--currency-isoCode').should('have.value', 'EUR');
        cy.get('#sw-field--currency-shortName').should('have.value', 'EUR');
        cy.get('#sw-field--currency-factor').should('have.value', '1');
    });

    it('@settings: can edit currency', { tags: ['pa-inventory', 'VUE3_SKIP'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'currencies',
                role: 'viewer',
            },
            {
                key: 'currencies',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/currency/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-settings-currency-list-grid').should('be.visible');
        cy.contains('.sw-data-grid__cell-content', 'US-Dollar').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/currency/*`,
            method: 'PATCH',
        }).as('saveCurrency');

        cy.contains('Euro')
            .click();

        // edit name
        cy.get('#sw-field--currency-name').clear().type('Kreuzer');

        // save currency
        cy.get(page.elements.currencySaveAction).click();

        // Verify creation
        cy.wait('@saveCurrency').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Kreuzer');
        cy.get('.sw-currency-list__content').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Kreuzer');
    });

    it('@settings: can create currency', { tags: ['pa-inventory', 'quarantined', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'currencies',
                role: 'viewer',
            },
            {
                key: 'currencies',
                role: 'editor',
            },
            {
                key: 'currencies',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/currency/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-settings-currency-list-grid').should('be.visible');
        cy.contains('.sw-data-grid__cell-content', 'US-Dollar').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/currency`,
            method: 'POST',
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
        cy.wait('@saveCurrency').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-currency-list__content').should('be.visible');
        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.currencyColumnName}`, 'Dukaten');
    });

    it('@settings: can delete currency', { tags: ['pa-inventory', 'VUE3_SKIP'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'currencies',
                role: 'viewer',
            },
            {
                key: 'currencies',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/currency/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-settings-currency-list-grid').should('be.visible');
        cy.contains('.sw-data-grid__cell-content', 'US-Dollar').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/currency/*`,
            method: 'delete',
        }).as('deleteCurrency');

        // filter currency via search bar
        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/**`,
            method: 'post',
        }).as('searchResultCall');

        cy.get('input.sw-search-bar__input').type('ZZ Yen').should('have.value', 'ZZ Yen');

        cy.wait('@searchResultCall')
            .its('response.statusCode').should('equal', 200);

        // Delete currency
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get('.sw-modal__body').should('be.visible');
        cy.contains('.sw-modal__body',
            'Are you sure you want to delete the currency "ZZ Yen"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        // Verify deletion
        cy.wait('@deleteCurrency').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.modal).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.currencyColumnName}`).should('not.exist');
    });
});
