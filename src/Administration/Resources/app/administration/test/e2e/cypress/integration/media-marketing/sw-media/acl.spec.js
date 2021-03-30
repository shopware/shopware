// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('Media: Test ACL privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
            });
    });

    it('@media: has no access to media module', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'property',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/media/index`);
        });

        // open media-payment without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.get('h1').contains('Access denied');
        cy.get('.sw-media-library').should('not.exist');
    });

    it('@media: can view media', () => {
        const page = new MediaPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'media',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/media/index`);
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

    it('@media: can edit media', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/media-method/*`,
            method: 'patch'
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

    it('@media: can create media', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'post'
        }).as('saveDataFileUpload');

        cy.route({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw_logo_white`,
            method: 'post'
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
        });

        cy.get('.sw-media-upload-v2__button-compact-upload').should('be.enabled');
        cy.get('.sw-media-upload-v2__button-context-menu').should('be.enabled');

        page.uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');

        cy.wait('@saveDataFileUpload').then((xhr) => {
            cy.awaitAndCheckNotification('File has been saved.');
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]')
            .should('be.visible');
    });

    it('@media: can delete media', () => {
        const page = new MediaPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'media',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/media/index`);
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

    it('@media: can edit settings for media folder', () => {
        // open context menu and open settings modal
        cy.get('.sw-media-grid-item__item--0 .sw-context-button__button').click({ force: true });
        cy.get('.sw-media-context-item__open-settings-action').click();

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
