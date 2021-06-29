// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Country: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('country');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/country/index`);
            });
    });

    it('@settings: create and read country', () => {
        cy.skipOnFeature('FEATURE_NEXT_14114');
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/country`,
            method: 'post'
        }).as('saveData');

        cy.get('a[href="#/sw/settings/country/create"]').click();

        // Create country
        cy.get('input[name=sw-field--country-name]').typeAndCheck('01.Niemandsland');

        cy.window().then((win) => {
            // Check tax free companies field exists and clicks
            cy.get('.sw-settings-country-detail__field-tax-free-companies input').should('be.visible');
            cy.get('.sw-settings-country-detail__field-tax-free-companies input').click();

            // Check validate vat id for correct format field exists and clicks
            cy.get('.sw-settings-country-detail__field-check-vatid-format input').should('be.visible');
            cy.get('.sw-settings-country-detail__field-check-vatid-format input').click();
        });

        cy.get(page.elements.countrySaveAction).click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).should('be.visible')
            .contains('01.Niemandsland');
    });

    it('@settings: update and read country', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/country/*`,
            method: 'patch'
        }).as('saveData');

        cy.get('.sw-settings-country-list-grid').should('be.visible');
        cy.clickContextMenuItem(
            '.sw-country-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('input[name=sw-field--country-name]').should('have.value', '1.Niemandsland');
        cy.get('input[name=sw-field--country-name]').clearTypeAndCheck('1.Niemandsland x2');
        cy.get(page.elements.countrySaveAction).click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).should('be.visible')
            .contains('1.Niemandsland x2');
    });

    it('@settings: delete country', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/country/*`,
            method: 'delete'
        }).as('deleteData');

        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete the country "1.Niemandsland"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        // Verify creation
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`)
            .should('not.have.value', '1.Niemandsland');
    });

    it('@settings: update currency dependent values and read country', () => {
        cy.onlyOnFeature('FEATURE_NEXT_14114');
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/country/*`,
            method: 'patch'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/currency`,
            method: 'post'
        }).as('searchCurrency');

        cy.clickContextMenuItem(
            '.sw-country-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-loader').should('not.exist');

        // Check tax free companies field exists and clicks
        cy.get('input[name="sw-field--country-customerTax-enabled"]').should('be.visible');
        cy.get('input[name="sw-field--country-customerTax-enabled"]').check().then(() => {
            cy.get('.sw-settings-country-general-customer-tax').should('be.visible');
            cy.get('input[name=sw-field--country-customerTax-amount]').should('be.visible');
        });

        cy.get('input[name=sw-field--country-customerTax-amount]').type('300');
        cy.get('.sw-settings-country-general__currency-dependent-modal').should('be.visible');
        cy.get('.sw-settings-country-general__currency-dependent-modal').click({ force: true }).then(() => {
            cy.get('.sw-settings-country-currency-dependent-modal').should('be.visible');
        });

        cy.get('.sw-settings-country-currency-hamburger-menu__button').click();
        cy.get('.sw-settings-country-currency-hamburger-menu__wrapper').should('be.visible');

        cy.get('.sw-settings-country-currency-hamburger-menu__wrapper ' +
            '.sw-settings-country-currency-hamburger-menu__item').eq(1).as('firstCurrency');
        cy.get('@firstCurrency').within(() => {
            cy.get('.sw-field__checkbox').click();
        });

        cy.get('.sw-data-grid__header').click();
        cy.get('.sw-data-grid__body .sw-data-grid__row').should(($row) => {
            expect($row).to.have.length(2);
        });

        cy.get('.sw-modal__footer .sw-button--primary').click();

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).should('be.visible')
            .contains('1.Niemandsland');

        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).click();

        cy.get('.sw-settings-country-general-customer-tax').should('be.visible');

        cy.get('#sw-field--country-customerTax-amount').eq(0).should('have.value', '300');
        cy.get('.sw-settings-country-general__customer-select-currency .sw-entity-single-select__selection-text')
            .should($selected => {
                expect($selected[0].outerText).to.contain('Euro');
            });

        cy.get('.sw-settings-country-general__currency-dependent-modal').should('be.visible');
        cy.get('.sw-settings-country-general__currency-dependent-modal').click({ force: true }).then(() => {
            cy.get('.sw-settings-country-currency-dependent-modal').should('be.visible');
            cy.get('.sw-data-grid__body .sw-data-grid__row--0').should('be.visible');
            cy.get('.sw-data-grid__body .sw-data-grid__row--1').should('be.visible');
        });
    });

    it('@settings: create and read country', () => {
        cy.onlyOnFeature('FEATURE_NEXT_14114');
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/country`,
            method: 'post'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/currency`,
            method: 'post'
        }).as('searchCurrency');

        cy.get('a[href="#/sw/settings/country/create"]').click();

        // Create country
        cy.get('input[name=sw-field--country-name]').typeAndCheck('01.Eastern Laos');

        // Check tax free companies field exists and clicks
        cy.get('input[name="sw-field--country-companyTax-enabled"]').check().then(() => {
            cy.get('.sw-settings-country-general-company-tax').should('be.visible');
            cy.get('input[name=sw-field--country-companyTax-amount]').should('be.visible');
        });

        cy.get('input[name=sw-field--country-companyTax-amount]').type('300');
        cy.get('.sw-settings-country-general__company-select-currency').click();
        cy.get('.sw-select-option--1').click();

        // Check validate vat id for correct format field exists and clicks
        const vatIdFormat = cy.get('.sw-settings-country-general__field-check-vatid-format .sw-field--switch__input');
        vatIdFormat.should('be.visible');
        vatIdFormat.click();

        cy.get(page.elements.countrySaveAction).click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-settings-country-general-company-tax').should('be.visible');
        cy.get('#sw-field--country-companyTax-amount').eq(0).should('have.value', '300');

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName}`).should('be.visible')
            .contains('01.Eastern Laos');
    });
});
