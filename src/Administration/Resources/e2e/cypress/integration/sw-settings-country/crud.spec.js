// / <reference types="Cypress" />

import SettingsPageObject from '../../support/pages/module/sw-settings.page-object';

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

    it('create and read country', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/country',
            method: 'post'
        }).as('saveData');

        cy.get('a[href="#/sw/settings/country/create"]').click();

        // Create country
        cy.get('input[name=sw-field--country-name]').type('01.Niemandsland');
        cy.get(page.elements.countrySaveAction).click();

        // Verify creation
        cy.wait('@saveData').then(() => {
            cy.get(page.elements.smartBarBack).click();
            cy.get(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`).should('be.visible')
                .contains('01.Niemandsland');
        });
    });

    it('update and read country', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/country/*',
            method: 'patch'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-country-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.gridRow}--0`
        );
        cy.get('input[name=sw-field--country-name]').clear();
        cy.get('input[name=sw-field--country-name]').type('1.Niemandsland x2');
        cy.get(page.elements.countrySaveAction).click();

        // Verify creation
        cy.wait('@saveData').then(() => {
            cy.get(page.elements.smartBarBack).click();
            cy.get(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`).should('be.visible')
                .contains('1.Niemandsland x2');
        });
    });

    it('delete country', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/country/*',
            method: 'delete'
        }).as('deleteData');

        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.gridRow}--0`
        );
        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete the country "1.Niemandsland"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.primaryButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        // Verify creation
        cy.wait('@deleteData').then(() => {
            cy.get(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`)
                .should('not.have.value', '1.Niemandsland');
        });
    });
});
