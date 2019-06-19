// / <reference types="Cypress" />

import SettingsPageObject from '../../support/pages/module/sw-settings.page-object';

describe('Currency: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('currency');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/currency/index`);
            });
    });

    it('create and read currency', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/currency?_response=true',
            method: 'post'
        }).as('saveData');

        cy.get('a[href="#/sw/settings/currency/create"]').click();

        // Create currency
        cy.get('input[name=sw-field--currency-name]').type('Dukaten');
        cy.get('input[name=sw-field--currency-isoCode]').type('D');
        cy.get('input[name=sw-field--currency-shortName]').type('D');
        cy.get('input[name=sw-field--currency-symbol]').type('D¥');
        cy.get('input[name=sw-field--currency-decimalPrecision]').type('2');
        cy.get('input[name=sw-field--currency-factor]').type('1.0076');
        cy.get(page.elements.currencySaveAction).click();

        // Verify creation
        cy.wait('@saveData').then(() => {
            cy.get(page.elements.smartBarBack).click();
            cy.get(`${page.elements.gridRow}--0 ${page.elements.currencyColumnName}`).should('be.visible')
                .contains('Dukaten');
        });
    });

    it('update and read currency', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/currency/*',
            method: 'patch'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-currency-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.gridRow}--3`
        );

        cy.get('input[name=sw-field--currency-name]').clear();
        cy.get('input[name=sw-field--currency-name]').type('Kreuzer');
        cy.get(page.elements.currencySaveAction).click();

        // Verify creation
        cy.wait('@saveData').then(() => {
            cy.get(page.elements.smartBarBack).click();
            cy.get(`${page.elements.gridRow}--1 ${page.elements.currencyColumnName}`).should('be.visible')
                .contains('Kreuzer');
        });
    });

    it('delete currency', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/currency/*',
            method: 'delete'
        }).as('deleteData');

        // Delete currency
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.gridRow}--3`
        );
        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete the currency "Yen"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.primaryButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        // Verify deletion
        cy.wait('@deleteData').then(() => {
            cy.awaitAndCheckNotification('Currency "Yen" has successfully been deleted.');
        });
    });
});
