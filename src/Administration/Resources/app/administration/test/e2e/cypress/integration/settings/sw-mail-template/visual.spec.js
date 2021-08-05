// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Mail templates: Check module navigation in settings', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of email templates module', () => {
        const page = new SettingsPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-mail-template').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.sortAndCheckListingAscViaColumn('Type', 'Contact form');

        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-mail-templates-list-grid .sw-data-grid__row--0').should('be.visible');

        // Delete manufacturer
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `#mailTemplateGrid ${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-media-upload-v2__dropzone').should('be.visible');
        cy.get('.sw-media-upload-v2__switch-mode .sw-context-button__button').should('be.visible');

        cy.takeSnapshot('[Mail templates] Details', '.sw-mail-template-detail');
    });
});
