// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Tax: Test crud operations', () => {
    beforeEach(() => {
        cy.createDefaultFixture('tax')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/tax/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: create and read tax', { tags: ['pa-customers-orders'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/tax`,
            method: 'POST',
        }).as('saveData');

        // Create tax
        cy.get('.sw-settings-tax-list-grid').should('be.visible');
        cy.get('a[href="#/sw/settings/tax/create"]').click();

        cy.get('input[name=sw-field--tax-name]').typeAndCheck('Very high tax');
        cy.get('input[name=sw-field--tax-taxRate]').type('99');

        cy.get(page.elements.taxSaveAction).click();

        // Verify tax
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--1 ${page.elements.taxColumnName}`, 'Very high tax')
            .should('be.visible');
    });

    it('@settings: update and read tax', { tags: ['pa-customers-orders'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/tax/*`,
            method: 'PATCH',
        }).as('saveData');

        // Edit tax' base data
        cy.get('.sw-settings-tax-list-grid').should('be.visible');
        cy.clickContextMenuItem(
            '.sw-tax-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get('input[name=sw-field--tax-name]').clearTypeAndCheck('Still high tax');
        cy.get(page.elements.taxSaveAction).click();

        // Verify tax
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.taxColumnName}`, 'Still high tax')
            .should('be.visible');
    });

    it('@settings: delete tax', { tags: ['pa-customers-orders'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/tax/*`,
            method: 'delete',
        }).as('deleteData');

        cy.get('.sw-settings-tax-list-grid').should('be.visible');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.contains('.sw-modal__body', 'Are you sure you want to delete the tax "High tax"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.modal).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--3`).should('not.exist');

        cy.contains('High tax').should('not.exist');
    });
});
