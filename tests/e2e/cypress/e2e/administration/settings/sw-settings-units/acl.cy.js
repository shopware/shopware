// / <reference types="Cypress" />
/**
 * @package inventory
 */
import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Unit: Test acl privileges', () => {
    beforeEach(() => {
        cy.createDefaultFixture('unit')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings @unit: has no access to scale unit module', { tags: ['pa-inventory', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/units/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open custom field without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.contains('h1', 'Access denied');
        cy.get('.sw-settings-units-grid').should('not.exist');

        // see no settings
        cy.get('.sw-admin-menu__item--sw-settings').should('not.exist');
    });

    it('@settings @unit: create and read unit', { tags: ['pa-inventory', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'scale_unit',
                role: 'viewer',
            },
            {
                key: 'scale_unit',
                role: 'editor',
            },
            {
                key: 'scale_unit',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/units/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/unit`,
            method: 'POST',
        }).as('saveData');

        // Go to unit module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-units').click();

        // Create unit
        cy.get('.sw-settings-units-grid').should('be.visible');
        cy.get('.sw-settings-units__create-action').click();

        // Wait for detail page skeleton to vanish
        cy.get('#sw-field--unit-name').should('be.visible');

        cy.get('#sw-field--unit-name').type('Kilogramm');
        cy.get('#sw-field--unit-shortCode').type('kg');

        cy.get('.sw-settings-units__create-action').click();

        // Verify creation
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/unit`,
            method: 'POST',
        }).as('getUnits');

        cy.get('.smart-bar__back-btn').click();
        cy.wait('@getUnits');
        cy.get('.sw-settings-units-grid').should('be.visible');

        cy.contains(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`, 'Kilogramm')
            .should('be.visible');
    });

    it('@settings @unit: update and read scale unit', { tags: ['pa-inventory', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'scale_unit',
                role: 'viewer',
            },
            {
                key: 'scale_unit',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/units/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/unit/*`,
            method: 'PATCH',
        }).as('saveData');

        // Go to unit module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-units').click();

        // Create unit
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/unit`,
            method: 'POST',
        }).as('loadUnit');

        cy.get('.sw-settings-units-grid').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name .sw-data-grid__cell-value').click();
        cy.wait('@loadUnit');
        cy.get('#sw-field--unit-shortCode').should('be.visible');

        cy.get('#sw-field--unit-shortCode').clear().type('kg');

        cy.get('.sw-settings-units__create-action').click();

        // Verify creation
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/unit`,
            method: 'POST',
        }).as('getUnits');

        cy.get('.smart-bar__back-btn').click();
        cy.wait('@getUnits');

        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--shortCode`, 'kg')
            .should('be.visible');
    });

    it('@settings @unit: delete scale unit', { tags: ['pa-inventory', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'scale_unit',
                role: 'viewer',
            },
            {
                key: 'scale_unit',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/units/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/unit/*`,
            method: 'delete',
        }).as('deleteData');

        // Go to unit module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-units').click();

        // Create unit
        cy.get('.sw-settings-units-grid').should('be.visible');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        // Verify creation
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-empty-state').should('be.visible');
    });
});
