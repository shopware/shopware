// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('Media: Dissolve folder', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('media-folder');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
            });
    });

    it('@media: dissolve folder', () => {
        const page = new MediaPageObject();

        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`
        );

        // Upload image in folder
        cy.get(page.elements.smartBarHeader).contains('A thing to fold about');
        runOn('chrome', () => {
            page.uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');
        });
        runOn('firefox', () => {
            // Upload medium
            cy.clickContextMenuItem(
                '.sw-media-upload-v2__button-url-upload',
                '.sw-media-upload-v2__button-context-menu'
            );
            page.uploadImageUsingUrl(`${Cypress.config('baseUrl')}/bundles/administration/static/img/sw-login-background.png`);
        });

        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]').should('be.visible');

        // Navigate back
        cy.get('.icon--multicolor-folder-breadcrumbs-back-to-root').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.icon--multicolor-folder-breadcrumbs-back-to-root').should('not.exist');

        // dissolve folder
        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            '.sw-media-context-item__dissolve-folder-action',
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`
        );
        cy.get(`${page.elements.modal}__body`)
            .contains('Are you sure you want to dissolve "A thing to fold about" ?');
        cy.get('.sw-media-modal-folder-dissolve__confirm').click();

        // Verify dissolved folder and existing image
        cy.get(page.elements.mediaNameLabel).contains('sw-login-background.png');
        cy.get('.sw-media-base-item__name[title="A thing to fold about"]').should('not.exist');
    });
});
