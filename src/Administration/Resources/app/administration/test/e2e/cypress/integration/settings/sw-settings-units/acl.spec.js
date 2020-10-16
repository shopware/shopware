// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Unit: Test acl privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('unit');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings @unit: has no access to scale unit module', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/units/index`);
        });

        // open custom field without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.get('h1').contains('Access denied');
        cy.get('.sw-settings-units-grid').should('not.exist');

        // see no settings
        cy.get('.sw-admin-menu__item--sw-settings').should('not.exist');
    });

    it('@settings @unit: create and read unit', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'scale_unit',
                role: 'viewer'
            },
            {
                key: 'scale_unit',
                role: 'editor'
            },
            {
                key: 'scale_unit',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/units/index`);
        });

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/unit',
            method: 'post'
        }).as('saveData');

        // Go to unit module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-units').click();

        // Create unit
        cy.get('.sw-settings-units-grid').should('be.visible');
        cy.get('.sw-settings-units__create-action').click();

        cy.get('.sw-data-grid__cell--name #sw-field--currentValue').type('Kilogramm');
        cy.get('.sw-data-grid__cell--shortCode #sw-field--currentValue').type('kg');

        cy.get('.sw-data-grid__inline-edit-save').click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`)
            .should('be.visible')
            .contains('Kilogramm');
    });

    it('@settings @unit: update and read scale unit', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'scale_unit',
                role: 'viewer'
            },
            {
                key: 'scale_unit',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/units/index`);
        });

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/unit/*',
            method: 'patch'
        }).as('saveData');

        // Go to unit module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-units').click();

        // Create unit
        cy.get('.sw-settings-units-grid').should('be.visible');
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--name').dblclick();

        cy.get('.sw-data-grid__cell--name #sw-field--currentValue').type('KG');

        cy.get('.sw-data-grid__inline-edit-save').click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .should('be.visible')
            .contains('KG');
    });

    it('@settings @unit: delete scale unit', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'scale_unit',
                role: 'viewer'
            },
            {
                key: 'scale_unit',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/units/index`);
        });

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/unit/*',
            method: 'delete'
        }).as('deleteData');

        // Go to unit module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-units').click();

        // Create unit
        cy.get('.sw-settings-units-grid').should('be.visible');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Verify creation
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-empty-state').should('be.visible');
    });
});
