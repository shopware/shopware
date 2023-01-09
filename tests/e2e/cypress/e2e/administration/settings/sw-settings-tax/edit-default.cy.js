/// <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Tax: Test default tax rates', () => {
    beforeEach(() => {
        cy.createDefaultFixture('tax', {
            id: '70359730b8f244bf94f4372ab4646fe5',
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/tax/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: should set new tax as default tax rate', { tags: ['pa-customers-orders'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/system-config`,
            method: 'POST',
        }).as('saveData');

        // Edit tax' base data
        cy.get('.sw-settings-tax-list-grid').should('be.visible');
        cy.clickContextMenuItem(
            '.sw-tax-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-settings-tax-detail__default-tax-rate').click();
        cy.get('.sw-loader').should('not.exist');

        cy.get(page.elements.taxSaveAction).click();

        // Verify tax
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Verify default tax in listing
        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.taxColumnName}`, 'High tax')
            .should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--default .is--active`)
            .should('be.visible');

        // Verify default tax in product creation
        cy.visit(`${Cypress.env('admin')}#/sw/product/create/base`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Value is the fixed ID, set through the fixture
        cy.get('#sw-field--product-taxId').should('have.value', '70359730b8f244bf94f4372ab4646fe5');
    });
});
