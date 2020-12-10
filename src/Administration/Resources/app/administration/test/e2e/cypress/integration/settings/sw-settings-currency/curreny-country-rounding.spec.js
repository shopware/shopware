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

    it('@settings: create and read currency with currency country roundings', () => {
        cy.window().then(() => {
            const page = new SettingsPageObject();
            // Request we want to wait for later
            cy.server();
            cy.route({
                url: `${Cypress.env('apiPath')}/currency`,
                method: 'post'
            }).as('saveData');

            cy.route({
                url: `${Cypress.env('apiPath')}/currency/**/country-roundings`,
                method: 'post'
            }).as('saveCurrencyCountry');


            cy.get('a[href="#/sw/settings/currency/create"]').click();

            // Create currency
            cy.get('input[name=sw-field--currency-name]').typeAndCheck('0000 Dukaten');
            cy.get('input[name=sw-field--currency-isoCode]').type('D');
            cy.get('input[name=sw-field--currency-shortName]').type('D');
            cy.get('input[name=sw-field--currency-symbol]').type('D¥');
            cy.get('input[name=sw-field--currency-factor]').type('1.0076');

            cy.get('input[name=sw-field--itemRounding-decimals]').clearTypeAndCheck('2');
            cy.get('.sw-settings-price-rounding__item-interval-select')
                .typeSingleSelectAndCheck('0.10', '.sw-settings-price-rounding__item-interval-select');
            cy.get('input[name=sw-field--totalRounding-decimals]').clearTypeAndCheck('15');
            cy.get('.sw-settings-price-rounding__grand-interval-select')
                .typeSingleSelectAndCheck('None', '.sw-settings-price-rounding__grand-interval-select');

            cy.get(page.elements.currencySaveAction).click();

            // Verify creation
            cy.wait('@saveData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });

            // Create new currency country rounding
            cy.get('.sw-settings-currency-detail__currency-country-toolbar-button').click();
            cy.get('.sw-settings-currency-country-modal__select-country')
                .typeSingleSelectAndCheck('Germany', '.sw-settings-currency-country-modal__select-country');


            cy.get('.sw-settings-currency-country-modal input[name=sw-field--itemRounding-decimals]')
                .clearTypeAndCheck('10');
            cy.get('.sw-settings-currency-country-modal input[name=sw-field--totalRounding-decimals]')
                .clearTypeAndCheck('2');
            cy.get('.sw-settings-currency-country-modal__button-save').click();

            // Verify creation
            cy.wait('@saveCurrencyCountry').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });

            cy.get('.sw-settings-currency-country-modal').should('not.exist');
            cy.get('.sw-settings-currency-detail__currency-country-list').should('be.visible');
            cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--country`).contains('Germany');

            cy.get(page.elements.smartBarBack).click();
            cy.get('.sw-currency-list__content').should('be.visible');
            cy.get(`${page.elements.dataGridRow}--0 ${page.elements.currencyColumnName}`).contains('0000 Dukaten');
        });
    });
});
