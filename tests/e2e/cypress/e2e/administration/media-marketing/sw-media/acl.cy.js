/**
 * @package content
 */
// / <reference types="Cypress" />

import MediaPageObject from '../../../../support/pages/module/sw-media.page-object';

describe('Media: Test ACL privileges', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
            });
    });

    it('@media: has no access to media module', { tags: ['pa-content-management'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'property',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/media/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open media-payment without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.contains('h1', 'Access denied');
        cy.get('.sw-media-library').should('not.exist');
    });

    it('@media: can view media', { tags: ['pa-content-management'] }, () => {
        const page = new MediaPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'media',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/media/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // check upload
        cy.get('.sw-media-upload-v2__button-compact-upload').should('be.disabled');
        cy.get('.sw-media-upload-v2__button-context-menu').should('be.disabled');

        cy.clickContextMenuItem(
            '.sw-media-context-item__show-media-action',
            page.elements.contextMenuButton,
            '.sw-media-grid-item__item--0',
            '',
            true
        );
        cy.get('.sw-media-sidebar__quickaction--disabled.quickaction--move').should('be.visible');
    });

    it('@media: can edit media', { tags: ['pa-content-management'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/media-method/*`,
            method: 'PATCH'
        }).as('savePayment');

        const page = new MediaPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'media',
                role: 'viewer'
            }, {
                key: 'media',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/media/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
        cy.get('.sw-media-upload-v2__button-compact-upload').should('be.disabled');
        cy.get('.sw-media-upload-v2__button-context-menu').should('be.disabled');

        // open media method
        cy.clickContextMenuItem(
            '.sw-media-context-item__show-media-action',
            page.elements.contextMenuButton,
            '.sw-media-grid-item__item--0',
            '',
            true
        );
        cy.get('.sw-media-sidebar__quickaction--disabled.quickaction--move').should('not.exist');
    });

    it('@media: can create media', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'POST'
        }).as('saveDataFileUpload');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw_logo_white`,
            method: 'POST'
        }).as('saveDataUrlUpload');

        const page = new MediaPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'media',
                role: 'viewer'
            }, {
                key: 'media',
                role: 'editor'
            }, {
                key: 'media',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/media/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.setEntitySearchable('media', ['fileName', 'title']);

        cy.get('.sw-media-upload-v2__button-compact-upload').should('be.enabled');
        cy.get('.sw-media-upload-v2__button-context-menu').should('be.enabled');

        page.uploadImageUsingFileUpload('img/sw-login-background.png');

        cy.wait('@saveDataFileUpload')
            .its('response.statusCode').should('equal', 204);
        cy.awaitAndCheckNotification('File has been saved.');
        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]')
            .should('be.visible');
    });

    it('@media: can delete media', { tags: ['pa-content-management'] }, () => {
        const page = new MediaPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'media',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/media/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // check upload
        cy.get('.sw-media-upload-v2__button-compact-upload').should('be.disabled');
        cy.get('.sw-media-upload-v2__button-context-menu').should('be.disabled');

        cy.clickContextMenuItem(
            '.sw-media-context-item__show-media-action',
            page.elements.contextMenuButton,
            '.sw-media-grid-item__item--0',
            '',
            true
        );
        cy.get('.sw-media-sidebar__quickaction--disabled.quickaction--move').should('be.visible');
        cy.get('.sw-media-sidebar__quickaction--disabled.quickaction--deleter').should('not.exist');
    });

    it('@media: can edit settings for media folder', { tags: ['pa-content-management'] }, () => {
        // open context menu and open settings modal
        cy.get('.sw-media-grid-item__item--0 .sw-context-button__button').click({ force: true });
        cy.get('.sw-media-context-item__open-settings-action').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // go to thumbnail tab
        cy.get('.sw-media-folder-settings__thumbnails-tab').click();

        // turn on thumbnail edit mode
        cy.get('.sw-media-modal-folder-settings__switch-mode').click();

        // delete thumbnail size
        cy.get('.sw-media-folder-settings__thumbnails-tab').click();

        // turn of thumbnail edit mode
        cy.get('.sw-media-modal-folder-settings__switch-mode').click();

        cy.get('.sw-modal .sw-button.sw-button--primary').click();

        cy.awaitAndCheckNotification('Settings have been saved.');
    });
});
