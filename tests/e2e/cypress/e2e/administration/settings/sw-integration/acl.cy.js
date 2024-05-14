// / <reference types="Cypress" />
import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

const uuid = require('uuid/v4');

function createTestRoleViaApi({ roleID, roleName }) {
    cy.window().then(($w) => {
        let headers = {
            Accept: 'application/vnd.api+json',
            Authorization: `Bearer ${$w.Shopware.Context.api.authToken.access}`,
            'Content-Type': 'application/json',
        };

        cy.request({
            url: `/${Cypress.env('apiPath')}/oauth/token`,
            method: 'POST',
            headers: headers,
            body: {
                grant_type: 'password',
                client_id: 'administration',
                scope: 'user-verified',
                username: 'admin',
                password: 'shopware',
            },
        }).then(response => {
            // overwrite headers with new scope
            headers = {
                Accept: 'application/vnd.api+json',
                Authorization: `Bearer ${response.body.access_token}`,
                'Content-Type': 'application/json',
            };

            return cy.request({
                url: `/${Cypress.env('apiPath')}/acl-role`,
                method: 'POST',
                headers: headers,
                body: {
                    id: roleID,
                    name: roleName,
                    privileges: [],
                },
            });
        });
    });
}

describe('Integration: Test acl privileges', () => {
    beforeEach(() => {
        cy.createDefaultFixture('integration')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: can view a list of integration', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'integration',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/integration/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // assert that there is an available list of integration
        cy.get(`${page.elements.integrationListContent}`).should('be.visible');
    });

    it('@settings: can create a integration', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'integration',
                role: 'viewer',
            },
            {
                key: 'integration',
                role: 'editor',
            },
            {
                key: 'integration',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/integration/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/integration`,
            method: 'POST',
        }).as('createIntegration');

        // go to create page
        cy.get('.sw-integration-list__add-integration-action').click();

        // clear old data and type another one in name field
        cy.get('#sw-field--currentIntegration-label')
            .clearTypeAndCheck('automation key');

        cy.get('.sw-integration-detail-modal__save-action').click();

        // Verify create a integration
        cy.wait('@createIntegration').its('response.statusCode').should('equal', 204);

        cy.contains('.sw-data-grid__cell-content a[href="#"]', 'automation key');
    });

    it('@settings: can edit a integration', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'integration',
                role: 'viewer',
            },
            {
                key: 'integration',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/integration/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/integration/*`,
            method: 'PATCH',
        }).as('editIntegration');

        // click on the first element in grid
        cy.get(`${page.elements.dataGridRow}--0`).contains('chat-key').click();

        cy.get('#sw-field--currentIntegration-label')
            .clearTypeAndCheck('chat-key-edited');

        cy.get('.sw-button--danger').click();

        cy.get('.sw-integration-detail-modal__save-action').click();

        // Verify edit a integration
        cy.wait('@editIntegration').its('response.statusCode').should('equal', 204);
        cy.contains('.sw-data-grid__cell-content a[href="#"]', 'chat-key-edited');
    });

    it('@settings: can edit a integration with roles', () => {
        // Insert some test roles
        createTestRoleViaApi({
            roleID: uuid().replace(/-/g, ''),
            roleName: 'e2e-test-role',
        });
        createTestRoleViaApi({
            roleID: uuid().replace(/-/g, ''),
            roleName: 'another-test-role',
        });

        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'integration',
                role: 'viewer',
            },
            {
                key: 'integration',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/integration/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/acl-role`,
            method: 'POST',
        }).as('loadAclRoles');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/integration/*`,
            method: 'PATCH',
        }).as('editIntegration');

        // click on the first element in grid
        cy.get(`${page.elements.dataGridRow}--0`).contains('chat-key').click();

        // disable administrator role
        // cy.contains('label', 'Administrator').click();

        cy.get('.sw-block-field__block > .sw-select__selection').click();

        cy.wait('@loadAclRoles')
            .its('response.statusCode').should('equal', 200);

        // add existing acl-roles
        cy.get('.sw-select-result-list__item-list')
            .contains('.sw-select-result', 'another-test-role')
            .click();
        cy.get('.sw-select-result-list__item-list')
            .contains('.sw-select-result', 'e2e-test-role')
            .click();

        cy.get('.sw-integration-detail-modal__save-action').click();

        // Verify edit a integration
        cy.wait('@editIntegration')
            .its('response.statusCode').should('equal', 204);

        cy.get('.sw-data-grid__cell--writeAccess').contains('span', 'another-test-role');
        cy.get('.sw-data-grid__cell--writeAccess').contains('span', 'e2e-test-role');

        // delete a integration role

        // click on the first element in grid
        cy.get(`${page.elements.dataGridRow}--0`).contains('chat-key').click();

        cy.get('.sw-block-field__block > .sw-select__selection').click();

        cy.wait('@loadAclRoles')
            .its('response.statusCode').should('equal', 200);

        // deselect 'another-test-role'
        cy.get('.sw-select-result-list__item-list')
            .contains('.sw-select-result', 'another-test-role')
            .click();

        cy.get('.sw-integration-detail-modal__save-action').click();

        // Verify edit a integration
        cy.wait('@editIntegration')
            .its('response.statusCode').should('equal', 204);

        cy.get('.sw-data-grid__cell--writeAccess').contains('span', 'e2e-test-role');
        cy.contains('another-test-role').should('not.exist');
    });

    it('@settings: can delete a integration', { tags: ['quarantined', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'integration',
                role: 'viewer',
            },
            {
                key: 'integration',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/integration/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/integration/*`,
            method: 'delete',
        }).as('deleteIntegration');

        // click on the first element in grid
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-button--primary.sw-button--small span.sw-button__content').contains('Delete').click();
        // Verify delete a integration
        cy.wait('@deleteIntegration').its('response.statusCode').should('equal', 204);
    });
});
