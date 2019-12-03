// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

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

    it('@settings: create and read currency', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/currency',
            method: 'post'
        }).as('saveData');

        cy.get('a[href="#/sw/settings/currency/create"]').click();

        // Create currency
        cy.get('input[name=sw-field--currency-name]').typeAndCheck('0000 Dukaten');
        cy.get('input[name=sw-field--currency-isoCode]').type('D');
        cy.get('input[name=sw-field--currency-shortName]').type('D');
        cy.get('input[name=sw-field--currency-symbol]').type('D¥');
        cy.get('input[name=sw-field--currency-decimalPrecision]').type('2');
        cy.get('input[name=sw-field--currency-factor]').type('1.0076');
        cy.get(page.elements.currencySaveAction).click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-currency-list__content').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.currencyColumnName}`).contains('Dukaten');
    });

    it('@settings: update and read currency', () => {
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
            `${page.elements.dataGridRow}--3`
        );

        cy.get('input[name=sw-field--currency-name]').clear();
        cy.get('input[name=sw-field--currency-name]').clearTypeAndCheck('Kreuzer');
        cy.get(page.elements.currencySaveAction).click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Kreuzer');
        cy.get('.sw-currency-list__content').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Kreuzer');
    });

    it('@settings: delete currency', () => {
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
            `${page.elements.dataGridRow}--8`
        );
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete the currency "ZZ Yen"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.primaryButton}`).click();

        // Verify deletion
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.modal).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--8 ${page.elements.currencyColumnName}`).should('not.exist');
    });
});
