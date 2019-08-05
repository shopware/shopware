// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('Media: Test thumbnails', () => {
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

    it('@content: create and use thumbnail sizes', () => {
        // TODO: Divide this tests into smaller ones if running time is allowing that

        const page = new MediaPageObject();

        cy.get(page.elements.loader).should('not.exist');

        cy.clickContextMenuItem(
            page.elements.showSettingsAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`
        );

        // Open thumbnail settings
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-media-folder-settings__thumbnails-tab').click();
        cy.get('button.sw-media-modal-folder-settings__switch-mode').click();
        cy.get('.sw-media-modal-folder-settings__thumbnails-list-container').click();

        // Set general thumbnail settings
        cy.get('input[name=sw-field--configuration-keepAspectRatio]').click(false);
        cy.get('input[name=sw-field--configuration-thumbnailQuality]').clear();
        cy.get('input[name=sw-field--configuration-thumbnailQuality]').type('90');

        // Create first thumbnail size with locked height
        page.setThumbnailSize({width: '800'}, 1);
        cy.get('button.sw-media-modal-folder-settings__switch-mode').click();
        cy.get(page.elements.saveSettingsAction).click();
        cy.awaitAndCheckNotification('Settings have been saved.');

        // Remove first thumbnail size and create second size with separate height afterwards
        cy.clickContextMenuItem(
            page.elements.showSettingsAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`
        );
        cy.get('.sw-media-folder-settings__thumbnails-tab').click();
        cy.get('button.sw-media-modal-folder-settings__switch-mode').click();
        cy.get('.sw-media-modal-folder-settings__entry--2 .sw-media-modal-folder-settings__delete-thumbnail').click();
        cy.get('.sw-media-modal-folder-settings__entry--2').should('not.exist');

        page.setThumbnailSize({width: '1920', height: '1080'}, 2);
        cy.get('input[name=thumbnail-size-1920-1080-active]').click();
        cy.get('button.sw-media-modal-folder-settings__switch-mode').click();
        cy.get(page.elements.saveSettingsAction).click();
        cy.awaitAndCheckNotification('Settings have been saved.');

        // Check inheritance of parent thumbnail settings and sizes
        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`
        );
        cy.get(page.elements.smartBarHeader).contains('A thing to fold about');

        page.createFolder('Child folder');
        cy.clickContextMenuItem(
            page.elements.showSettingsAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`
        );
        cy.get('.sw-media-folder-settings__thumbnails-tab').click();
        cy.get('input[name=sw-field--configuration-keepAspectRatio]').should('not.be.selected');
        cy.get('.sw-media-modal-folder-settings__thumbnail-size-entry label').contains('1920x1080');
        cy.get('input[name=thumbnail-size-1920-1080-active]').should('be.checked');

        // Deactivate inheritance
        cy.get('input[name=sw-field--folder-useParentConfiguration]').click(false);
        cy.get('input[name=sw-field--configuration-keepAspectRatio]').click();
        cy.get('.sw-media-modal-folder-settings__switch-mode').click();
        cy.get('.sw-media-modal-folder-settings__entry--2 .sw-media-modal-folder-settings__delete-thumbnail').click();
        cy.get('.sw-media-modal-folder-settings__thumbnail-size-entry--2').should('not.exist');
        cy.get('.sw-media-modal-folder-settings__switch-mode').click();
        cy.get(page.elements.saveSettingsAction).click();
        cy.awaitAndCheckNotification('Settings have been saved.');

        // Verify deactivated inheritance
        cy.get('.icon--multicolor-folder-breadcrumbs-back-to-root').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.folderNameLabel).contains('A thing to fold about');
        cy.clickContextMenuItem(
            page.elements.showSettingsAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`
        );
        cy.get('.sw-media-folder-settings__thumbnails-tab').click();
        cy.get('input[name=sw-field--configuration-keepAspectRatio]').should('not.be.selected');
        cy.get('input[name=sw-field--configuration-keepAspectRatio]').should('not.be.selected');
    });
});
