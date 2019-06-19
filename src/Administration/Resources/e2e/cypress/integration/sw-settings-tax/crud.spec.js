// / <reference types="Cypress" />

import SettingsPageObject from '../../support/pages/module/sw-settings.page-object';

describe('Tax: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('tax');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/tax/index`);
            });
    });

    it('create and read tax', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/tax?_response=true',
            method: 'post'
        }).as('saveData');

        // Create tax
        cy.get('a[href="#/sw/settings/tax/create"]').click();
        cy.get('input[name=sw-field--tax-name]').type('Very high tax');
        cy.get('input[name=sw-field--tax-taxRate]').type('99');

        cy.get(page.elements.taxSaveAction).click();

        // Verify tax
        cy.wait('@saveData').then(() => {
            cy.get(page.elements.smartBarBack).click();
            cy.get(`${page.elements.gridRow}--3 ${page.elements.taxColumnName}`).should('be.visible')
                .contains('Very high tax');
        });
    });

    it('update and read tax', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/tax/*',
            method: 'patch'
        }).as('saveData');

        // Edit tax' base data
        cy.clickContextMenuItem(
            '.sw-tax-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.gridRow}--2`
        );

        cy.get('input[name=sw-field--tax-name]').clear();
        cy.get('input[name=sw-field--tax-name]').type('Still high tax');
        cy.get(page.elements.taxSaveAction).click();

        // Verify tax
        cy.wait('@saveData').then(() => {
            cy.get(page.elements.smartBarBack).click();
            cy.get(`${page.elements.gridRow}--2 ${page.elements.taxColumnName}`).should('be.visible')
                .contains('Still high tax');
        });
    });

    it('delete tax', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/tax/*',
            method: 'delete'
        }).as('deleteData');

        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.gridRow}--2`
        );

        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete the tax "High tax"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.primaryButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        cy.wait('@deleteData').then(() => {
            cy.awaitAndCheckNotification('Tax "High tax" has successfully been deleted.');
        });
    });
});
