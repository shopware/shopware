/**
 * @package content
 */
// / <reference types="Cypress" />

import MediaPageObject from '../../../../support/pages/module/sw-media.page-object';

describe('Media: Move folder and image', () => {
    beforeEach(() => {
        cy.createDefaultFixture('media-folder', {
            name: '1st folder',
        }).then(() => {
            return cy.createDefaultFixture('media-folder', {
                name: '2nd folder',
            });
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@media: move folder and medium', { tags: ['pa-content-management'] }, () => {
        const page = new MediaPageObject();

        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true,
        );
        cy.contains(page.elements.smartBarHeader, '1st folder');
        cy.setEntitySearchable('media', ['fileName', 'title']);
        // Upload image in folder
        page.uploadImageUsingFileUpload('img/sw-login-background.png');
        cy.awaitAndCheckNotification('File has been saved.');
        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]')
            .should('be.visible');

        // Navigate back
        cy.get('.icon--regular-double-chevron-left-s').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.icon--regular-double-chevron-left-s').should('not.exist');

        // Upload another image
        page.uploadImageUsingFileUpload('img/sw-test-image.png');
        cy.awaitAndCheckNotification('File has been saved.');

        // Move image to second folder
        page.moveMediaItem('sw-test-image.png', {
            itemType: 'medium',
            listingPosition: 1,
        });

        // Move first folder to second one
        page.moveMediaItem('1st folder', {
            itemType: 'folder',
        });

        // Verify movement
        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true,
        );
        cy.get(page.elements.loader).should('not.exist');
        cy.contains(page.elements.smartBarHeader, '2nd folder');
        cy.contains(`${page.elements.gridItem}--0 ${page.elements.baseItemName}`, '1st folder');
        cy.contains(`${page.elements.gridItem}--1 ${page.elements.baseItemName}`, 'sw-test-image.png');

        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true,
        );
        cy.get(page.elements.loader).should('not.exist');
        cy.contains(page.elements.smartBarHeader, '1st folder');
        cy.contains(`${page.elements.gridItem}--0 ${page.elements.baseItemName}`,
            'sw-login-background.png');
    });
});
