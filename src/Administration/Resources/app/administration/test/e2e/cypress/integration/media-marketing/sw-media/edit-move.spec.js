// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('Media: Move folder and image', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('media-folder', {
                    name: '1st folder'
                });
            })
            .then(() => {
                return cy.createDefaultFixture('media-folder', {
                    name: '2nd folder'
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
            });
    });

    it('@media: move folder and medium', () => {
        const page = new MediaPageObject();

        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
        );
        cy.get(page.elements.smartBarHeader).contains('1st folder');

        // Upload image in folder
        page.uploadImageUsingFileUpload('img/sw-login-background.png');
        cy.awaitAndCheckNotification('File has been saved.');
        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]')
            .should('be.visible');

        // Navigate back
        cy.get('.icon--multicolor-folder-breadcrumbs-back-to-root').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.icon--multicolor-folder-breadcrumbs-back-to-root').should('not.exist');

        // Upload another image
        page.uploadImageUsingFileUpload('img/sw-test-image.png');
        cy.awaitAndCheckNotification('File has been saved.');

        // Move image to second folder
        page.moveMediaItem('sw-test-image.png', {
            itemType: 'medium',
            listingPosition: 1
        });

        // Move first folder to second one
        page.moveMediaItem('1st folder', {
            itemType: 'folder'
        });

        // Verify movement
        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
        );
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).contains('2nd folder');
        cy.get(`${page.elements.gridItem}--0 ${page.elements.baseItemName}`).contains('1st folder');
        cy.get(`${page.elements.gridItem}--1 ${page.elements.baseItemName}`).contains('sw-test-image.png');

        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
        );
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).contains('1st folder');
        cy.get(`${page.elements.gridItem}--0 ${page.elements.baseItemName}`)
            .contains('sw-login-background.png');
    });
});
