/// <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

    describe('Media: Test thumbnails', { browser: "chrome"},() => {
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

    it.skip('@media: create and use thumbnail sizes', () => {
        // TODO: Divide this tests into smaller ones if running time is allowing that

        const page = new MediaPageObject();

        cy.get(page.elements.loader).should('not.exist');

        cy.clickContextMenuItem(
            page.elements.showSettingsAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`
        );
        cy.get('.sw-media-folder-settings__thumbnails-tab').click();

        cy.get('button.sw-media-modal-folder-settings__switch-mode').click();
        cy.get('.sw-media-modal-folder-settings__entry--2 .sw-media-modal-folder-settings__delete-thumbnail').click();

        page.setThumbnailSize({width: '1920', height: '1080'}, 2);
        cy.get('input[name=thumbnail-size-1920-1080-active]').click();
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
        cy.get('input[name=sw-field--folder-useParentConfiguration]').uncheck();
        cy.get('input[name=sw-field--configuration-createThumbnails]').check();
        cy.get('input[name=sw-field--configuration-keepAspectRatio]').check();
        cy.get(page.elements.saveSettingsAction).click();
    });
});
