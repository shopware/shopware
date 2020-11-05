// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Integration: crud integrations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: can create a new integration', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/integration',
            method: 'post'
        }).as('createIntegration');

        // go to integration module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('.sw-settings__tab-system').click();
        cy.get('#sw-integration').click();

        // go to create page
        cy.get('.sw-integration-list__add-integration-action').click();

        // clear old data and type another one in name field
        cy.get('#sw-field--currentIntegration-label')
            .clear()
            .type('chat-key');

        cy.get('.sw-integration-detail-modal__save-action').click();

        // Verify create a integration
        cy.wait('@createIntegration').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-data-grid__cell-content a[href="#"]').contains('chat-key');
    });

    it('@settings: can create a new integration with double click', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/integration',
            method: 'post'
        }).as('createIntegration');

        // go to integration module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('.sw-settings__tab-system').click();
        cy.get('#sw-integration').click();

        // go to create page
        cy.get('.sw-integration-list__add-integration-action').dblclick();

        // clear old data and type another one in name field
        cy.get('#sw-field--currentIntegration-label')
            .clear()
            .type('chat-key');

        cy.get('.sw-integration-detail-modal__save-action').click();

        // Verify create a integration
        cy.wait('@createIntegration').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-data-grid__cell-content a[href="#"]').contains('chat-key');
    });

    it('@settings: can edit a integration', () => {
        const page = new SettingsPageObject();
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/integration',
            method: 'post'
        }).as('createIntegration');
        cy.route({
            url: '/api/v*/integration/*',
            method: 'patch'
        }).as('editIntegration');

        // go to integration module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('.sw-settings__tab-system').click();
        cy.get('#sw-integration').click();

        // go to create page
        cy.get('.sw-integration-list__add-integration-action').click();

        // clear old data and type another one in name field
        cy.get('#sw-field--currentIntegration-label')
            .clear()
            .type('chat-key');

        cy.get('.sw-integration-detail-modal__save-action').click();

        // Verify create a integration
        cy.wait('@createIntegration').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // click on the first element in grid
        cy.get(`${page.elements.dataGridRow}--0`).contains('chat-key').click();

        cy.get('#sw-field--currentIntegration-label')
            .clear()
            .type('chat-key-edited');

        cy.get('.sw-button--danger').click();

        cy.get('.sw-integration-detail-modal__save-action').click();

        // Verify edit a integration
        cy.wait('@editIntegration').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });

    it('@settings: can delete a integration', () => {
        const page = new SettingsPageObject();
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/integration',
            method: 'post'
        }).as('createIntegration');
        cy.route({
            url: '/api/v*/integration/*',
            method: 'delete'
        }).as('deleteIntegration');

        // go to integration module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('.sw-settings__tab-system').click();
        cy.get('#sw-integration').click();

        // go to create page
        cy.get('.sw-integration-list__add-integration-action').click();

        // clear old data and type another one in name field
        cy.get('#sw-field--currentIntegration-label')
            .clear()
            .type('chat-key');

        cy.get('.sw-integration-detail-modal__save-action').click();

        // Verify create a integration
        cy.wait('@createIntegration').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-button--primary.sw-button--small span.sw-button__content').contains('Delete').click();
        // Verify delete a integration
        cy.wait('@deleteIntegration').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });
});
