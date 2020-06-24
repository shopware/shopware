/// <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

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

    it('@settings: create and read tax', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/tax',
            method: 'post'
        }).as('saveData');

        // Create tax
        cy.get('.sw-settings-tax-list-grid').should('be.visible');
        cy.get('a[href="#/sw/settings/tax/create"]').click();

        cy.get('input[name=sw-field--tax-name]').typeAndCheck('Very high tax');
        cy.get('input[name=sw-field--tax-taxRate]').type('99');

        cy.get(page.elements.taxSaveAction).click();

        // Verify tax
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--4 ${page.elements.taxColumnName}`).should('be.visible')
            .contains('Very high tax');
    });

    it('@settings: update and read tax', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/tax/*',
            method: 'patch'
        }).as('saveData');

        // Edit tax' base data
        cy.get('.sw-settings-tax-list-grid').should('be.visible');
        cy.clickContextMenuItem(
            '.sw-tax-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('input[name=sw-field--tax-name]').clearTypeAndCheck('Still high tax');
        cy.get(page.elements.taxSaveAction).click();

        // Verify tax
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--3 ${page.elements.taxColumnName}`).should('be.visible')
            .contains('Still high tax');
    });

    it('@settings: delete tax', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/tax/*',
            method: 'delete'
        }).as('deleteData');

        cy.get('.sw-settings-tax-list-grid').should('be.visible');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete the tax "High tax"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.modal).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--3`).should('not.exist');

        cy.contains('High tax').should('not.exist');
    });
});
