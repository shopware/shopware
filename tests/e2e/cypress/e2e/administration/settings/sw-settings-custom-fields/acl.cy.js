// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Custom fields: Test ACL privileges', () => {
    beforeEach(() => {
        cy.createDefaultFixture('custom-field-set')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/index`);
            });
    });

    it('@settings @customField: has no access to custom field module', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open custom field without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.contains('h1', 'Access denied');
        cy.get('.sw-settings-custom-field-set-list__card').should('not.exist');
    });

    it('@settings @customField: can view custom field set', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'custom_field',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-settings-custom-field-set-list__button-create.sw-button--disabled')
            .should('be.visible');

        // open custom field
        cy.get('.sw-custom-field-set-list__column-name').first().click();
        cy.get('.sw-settings-set-detail__save-action').should('be.disabled');
    });

    it('@settings @customField: can edit custom field set', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/custom-field-set`,
            method: 'POST',
        }).as('saveData');

        cy.loginAsUserWithPermissions([
            {
                key: 'custom_field',
                role: 'viewer',
            }, {
                key: 'custom_field',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-settings-custom-field-set-list__button-create.sw-button--disabled')
            .should('be.visible');
        cy.get('.sw-custom-field-set-list__column-name').first().click();
        cy.get('.sw-settings-set-detail__save-action').should('be.enabled');

        cy.get('#sw-field--set-position').type('2');
        cy.get('.sw-settings-set-detail__save-action').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
    });

    it('@settings @customfield: can create custom field set', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/custom-field-set`,
            method: 'POST',
        }).as('saveData');

        cy.loginAsUserWithPermissions([
            {
                key: 'custom_field',
                role: 'viewer',
            }, {
                key: 'custom_field',
                role: 'editor',
            }, {
                key: 'custom_field',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/custom/field/create`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-settings-set-detail__save-action').should('be.enabled');

        cy.get('#sw-field--set-name').clearTypeAndCheck('my_custom_field');

        cy.get('.sw-custom-field-translated-labels input').clearTypeAndCheck('My custom field set');

        cy.get('.sw-select').click();
        cy.contains('.sw-select-result', 'Products').click({ force: true });
        cy.get('h2').click();
        cy.get('.sw-select__results-list').should('not.exist');
        cy.contains('.sw-label', 'Products');

        cy.get('.sw-empty-state').should('exist');

        // saving custom field
        cy.get('.sw-settings-set-detail__save-action').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
    });

    it('@settings @customfield: can delete custom field set', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/custom-field-set/*`,
            method: 'delete',
        }).as('deleteData');

        cy.loginAsUserWithPermissions([
            {
                key: 'custom_field',
                role: 'viewer',
            }, {
                key: 'custom_field',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open custom field
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.gridRow}--0`,
        );

        cy.get('.sw-modal').should('be.visible');
        cy.contains('.sw-modal__body', 'Do you really want to delete the set "My custom field" ?');
        cy.get('.sw-button--danger').click();

        // Verify deletion
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-modal').should('not.exist');

        cy.get('.sw-empty-state').should('exist');
        cy.contains('.sw-empty-state__title', 'No custom fields yet.');
    });
});
