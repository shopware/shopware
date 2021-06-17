// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('Media: Test thumbnails', { browser: 'chrome' }, () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('media-folder', {
                    configuration: {
                        createThumbnails: true,
                        thumbnailQuality: 50,
                        keepAspectRatio: false,
                        mediaThumbnailSizes: [
                            { id: '912ba2cfbf654aeca677d514ebf5c772', width: 5000, height: 2500 }
                        ]
                    }
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
            });
    });

    it('@media: create and delete thumbnail sizes', () => {
        const page = new MediaPageObject();

        cy.get(page.elements.loader).should('not.exist');

        // Open folder settings
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).click();
        cy.clickContextMenuItem(
            page.elements.showSettingsAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
        );

        cy.get('.sw-media-folder-settings__thumbnails-tab').click();

        // Start the editing mode for thumbnail sizes
        cy.get('button.sw-media-modal-folder-settings__switch-mode').click();

        // Delete a thumbnail size
        cy.get('.sw-media-modal-folder-settings__entry--2 .sw-media-modal-folder-settings__delete-thumbnail').click();


        page.setThumbnailSize({ width: '3840', height: '2160' }, 2);

        cy.get('button.sw-media-modal-folder-settings__switch-mode').click();

        cy.get('input[name=thumbnail-size-3840-2160-active]').click();
        cy.get(page.elements.saveSettingsAction).click();
        cy.awaitAndCheckNotification('Settings have been saved.');

        // Verify the settings were saved successfully
        // Open folder settings
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).click();
        cy.clickContextMenuItem(
            page.elements.showSettingsAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
        );

        cy.get('.sw-media-folder-settings__thumbnails-tab').click();
        cy.get('input[name=thumbnail-size-3840-2160-active]').should('be.checked');
        cy.get('input[name=thumbnail-size-5000-2500-active]').should('be.checked');
        cy.get('input[name=thumbnail-size-1920-1920-active]').should('not.exist');
    });

    it('@media: inherit thumbnail settings', () => {
        const page = new MediaPageObject();

        cy.get(page.elements.loader).should('not.exist');

        // Open the media folder
        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
        );

        cy.get(page.elements.smartBarHeader).contains('A thing to fold about');

        page.createFolder('Child folder');

        // Open settings
        cy.clickContextMenuItem(
            page.elements.showSettingsAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
        );

        cy.get('.sw-media-folder-settings__thumbnails-tab').click();
        cy.get('input[name=sw-field--configuration-keepAspectRatio]').should('not.be.checked');
        cy.get('input[name=sw-field--configuration-createThumbnails]').should('be.checked');
        cy.get('input[name=thumbnail-size-5000-2500-active]').should('be.checked');
        cy.get('input[name=thumbnail-size-1920-1920-active]').should('not.be.checked');
        cy.get('input[name=thumbnail-size-800-800-active]').should('not.be.checked');
        cy.get('input[name=thumbnail-size-400-400-active]').should('not.be.checked');

        // Deactivate inheritance
        cy.get('input[name=sw-field--folder-useParentConfiguration]').uncheck();
        cy.get('input[name=sw-field--configuration-keepAspectRatio]').check();
        cy.get('input[name=thumbnail-size-1920-1920-active]').check();
        cy.get(page.elements.saveSettingsAction).click();

        // Verify the settings were saved successfully
        // Open folder settings
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).click();
        cy.clickContextMenuItem(
            page.elements.showSettingsAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
        );

        cy.get('.sw-media-folder-settings__thumbnails-tab').click();
        cy.get('input[name=sw-field--folder-useParentConfiguration]').should('not.be.checked');
        cy.get('input[name=sw-field--configuration-createThumbnails]').should('be.checked');
        cy.get('input[name=sw-field--configuration-keepAspectRatio]').should('be.checked');
        cy.get('input[name=thumbnail-size-1920-1920-active]').should('be.checked');
        cy.get('input[name=thumbnail-size-5000-2500-active]').should('be.checked');
    });
});
